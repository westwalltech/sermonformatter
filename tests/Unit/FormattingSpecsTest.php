<?php

use Illuminate\Support\Facades\File;
use NewSong\SermonFormatter\Services\FormattingSpecs;

beforeEach(function () {
    $this->specsPath = storage_path('sermon-formatter/formatting-specs.md');

    // Clean up before each test
    if (File::exists($this->specsPath)) {
        File::delete($this->specsPath);
    }
});

afterEach(function () {
    if (File::exists($this->specsPath)) {
        File::delete($this->specsPath);
    }
});

it('returns default specs when no custom specs exist', function () {
    $specs = new FormattingSpecs;

    $content = $specs->get();

    expect($content)->toContain('sermon notes formatter')
        ->and($content)->toContain('Formatting Rules');
});

it('saves and retrieves custom specs', function () {
    $specs = new FormattingSpecs;
    $custom = 'Custom formatting instructions';

    $specs->save($custom);

    expect($specs->get())->toBe($custom);
});

it('resets to default specs', function () {
    $specs = new FormattingSpecs;
    $specs->save('Custom content');

    $specs->reset();

    expect(File::exists($this->specsPath))->toBeFalse()
        ->and($specs->get())->toContain('sermon notes formatter');
});

it('creates directory if it does not exist', function () {
    $directory = dirname($this->specsPath);
    if (File::isDirectory($directory)) {
        File::deleteDirectory($directory);
    }

    $specs = new FormattingSpecs;
    $specs->save('Test content');

    expect(File::exists($this->specsPath))->toBeTrue();
});

it('builds system prompt from specs', function () {
    $specs = new FormattingSpecs;

    $prompt = $specs->buildSystemPrompt();

    expect($prompt)->toBeString()
        ->and($prompt)->not->toBeEmpty();
});
