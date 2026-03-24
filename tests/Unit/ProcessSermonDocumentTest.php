<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use NewSong\SermonFormatter\Jobs\ProcessSermonDocument;
use NewSong\SermonFormatter\Models\ProcessingLog;
use NewSong\SermonFormatter\Services\ClaudeClient;
use NewSong\SermonFormatter\Services\ClaudeResponse;
use NewSong\SermonFormatter\Services\DocumentParser;
use NewSong\SermonFormatter\Services\FormattingSpecs;
use NewSong\SermonFormatter\Services\MarkdownToBard;
use Statamic\Contracts\Entries\EntryRepository;

uses(RefreshDatabase::class);

/**
 * Build a fake entry object that mimics the Statamic Entry interface for tests.
 */
function makeFakeEntry(): object
{
    return new class
    {
        public array $data = ['sermon_source' => []];

        public bool $savedQuietly = false;

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->data[$key] ?? $default;
        }

        public function set(string $key, mixed $value): static
        {
            $this->data[$key] = $value;

            return $this;
        }

        public function saveQuietly(): void
        {
            $this->savedQuietly = true;
        }
    };
}

/**
 * Bind a mock EntryRepository into the container that returns the given fake entry.
 */
function bindFakeEntryRepository(object $fakeEntry): void
{
    $repo = Mockery::mock(EntryRepository::class);
    $repo->shouldReceive('find')->andReturn($fakeEntry);
    app()->instance(EntryRepository::class, $repo);
}

beforeEach(function () {
    // Create temp upload directory and file
    $this->tempDir = storage_path('app/sermon-uploads/test');
    File::ensureDirectoryExists($this->tempDir);
    $this->filePath = $this->tempDir.'/test-sermon.docx';
    File::put($this->filePath, 'dummy content');

    $this->entryId = 'test-entry-uuid-1234';

    $this->log = ProcessingLog::create([
        'entry_id' => $this->entryId,
        'collection' => 'messages',
        'file_name' => 'test-sermon.docx',
        'status' => 'pending',
    ]);
});

afterEach(function () {
    if (File::exists($this->filePath)) {
        File::delete($this->filePath);
    }
});

it('marks log with warning when Claude response is truncated', function () {
    $fakeEntry = makeFakeEntry();
    bindFakeEntryRepository($fakeEntry);

    $truncatedResponse = new ClaudeResponse(
        content: '# Sermon\n\nThis content was cut off mid-sent',
        inputTokens: 5000,
        outputTokens: 8192,
        model: 'claude-sonnet-4-20250514',
        stopReason: 'max_tokens',
    );

    $parser = Mockery::mock(DocumentParser::class);
    $parser->shouldReceive('parse')->andReturn('Raw sermon text content');

    $claude = Mockery::mock(ClaudeClient::class);
    $claude->shouldReceive('send')->andReturn($truncatedResponse);

    $specs = Mockery::mock(FormattingSpecs::class);
    $specs->shouldReceive('buildSystemPrompt')->andReturn('Format this sermon');

    $converter = Mockery::mock(MarkdownToBard::class);
    $converter->shouldReceive('convert')->andReturn([['type' => 'paragraph']]);

    $job = new ProcessSermonDocument(
        entryId: $this->entryId,
        collection: 'messages',
        filePath: $this->filePath,
        fileName: 'test-sermon.docx',
        logId: $this->log->id,
    );

    $job->handle($parser, $claude, $specs, $converter);

    $this->log->refresh();

    expect($this->log->stop_reason)->toBe('max_tokens')
        ->and($this->log->status)->toBe('completed')
        ->and($this->log->error)->toContain('truncated');
});

it('saves content normally when stop_reason is end_turn', function () {
    $fakeEntry = makeFakeEntry();
    bindFakeEntryRepository($fakeEntry);

    $normalResponse = new ClaudeResponse(
        content: '# Sermon\n\nThis is the full sermon content.',
        inputTokens: 3000,
        outputTokens: 1500,
        model: 'claude-sonnet-4-20250514',
        stopReason: 'end_turn',
    );

    $parser = Mockery::mock(DocumentParser::class);
    $parser->shouldReceive('parse')->andReturn('Raw sermon text content');

    $claude = Mockery::mock(ClaudeClient::class);
    $claude->shouldReceive('send')->andReturn($normalResponse);

    $specs = Mockery::mock(FormattingSpecs::class);
    $specs->shouldReceive('buildSystemPrompt')->andReturn('Format this sermon');

    $converter = Mockery::mock(MarkdownToBard::class);
    $converter->shouldReceive('convert')->andReturn([['type' => 'paragraph'], ['type' => 'paragraph']]);

    $job = new ProcessSermonDocument(
        entryId: $this->entryId,
        collection: 'messages',
        filePath: $this->filePath,
        fileName: 'test-sermon.docx',
        logId: $this->log->id,
    );

    $job->handle($parser, $claude, $specs, $converter);

    $this->log->refresh();

    expect($this->log->stop_reason)->toBe('end_turn')
        ->and($this->log->status)->toBe('completed')
        ->and($this->log->error)->toBeNull();
});

it('preserves uploaded file when job fails', function () {
    $fakeEntry = makeFakeEntry();
    bindFakeEntryRepository($fakeEntry);

    $parser = Mockery::mock(DocumentParser::class);
    $parser->shouldReceive('parse')->andThrow(new RuntimeException('Parse error'));

    $specs = Mockery::mock(FormattingSpecs::class);
    $claude = Mockery::mock(ClaudeClient::class);
    $converter = Mockery::mock(MarkdownToBard::class);

    $job = new ProcessSermonDocument(
        entryId: $this->entryId,
        collection: 'messages',
        filePath: $this->filePath,
        fileName: 'test-sermon.docx',
        logId: $this->log->id,
    );

    try {
        $job->handle($parser, $claude, $specs, $converter);
    } catch (RuntimeException) {
        // expected
    }

    expect(File::exists($this->filePath))->toBeTrue();
});
