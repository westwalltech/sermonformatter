<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use NewSong\SermonFormatter\Models\ProcessingLog;
use NewSong\SermonFormatter\Services\ClaudeClient;
use NewSong\SermonFormatter\Services\DocumentParser;
use NewSong\SermonFormatter\Services\FormattingSpecs;
use NewSong\SermonFormatter\Services\MarkdownToBard;
use NewSong\SermonFormatter\Support\Logger;
use Statamic\Facades\Entry;

class ProcessSermonDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $retryAfter;

    public function __construct(
        public string $entryId,
        public string $collection,
        public string $filePath,
        public string $fileName,
        public int $logId,
        public ?string $targetField = null,
    ) {
        $this->tries = config('sermon-formatter.queue.retries', 3);
        $this->retryAfter = config('sermon-formatter.queue.retry_after', 120);
        $this->onQueue(config('sermon-formatter.queue.name', 'default'));
        $this->targetField = $targetField ?? config('sermon-formatter.processing.target_field', 'notes');
    }

    public function handle(
        DocumentParser $parser,
        ClaudeClient $claude,
        FormattingSpecs $specs,
        MarkdownToBard $converter,
    ): void {
        $log = ProcessingLog::find($this->logId);
        if (! $log) {
            Logger::error('Processing log not found', ['log_id' => $this->logId]);

            return;
        }

        $startTime = microtime(true);
        $log->markProcessing();

        Logger::info('Starting sermon processing', [
            'entry_id' => $this->entryId,
            'file' => $this->fileName,
        ]);

        try {
            // Step 1: Parse document
            $rawText = $parser->parse($this->filePath);

            if (empty(trim($rawText))) {
                throw new \RuntimeException('Document appears to be empty after parsing.');
            }

            Logger::info('Document parsed', [
                'characters' => strlen($rawText),
            ]);

            // Step 2: Get formatting specs and send to Claude
            $systemPrompt = $specs->buildSystemPrompt();
            $response = $claude->send($systemPrompt, $rawText);

            Logger::info('Claude response received', [
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
            ]);

            // Step 3: Convert markdown to Bard content
            $bardContent = $converter->convert($response->content);

            // Step 4: Update the entry
            $entry = Entry::find($this->entryId);
            if (! $entry) {
                throw new \RuntimeException("Entry {$this->entryId} not found.");
            }

            $entry->set($this->targetField, $bardContent);
            $entry->set('sermon_source', array_merge(
                $entry->get('sermon_source', []),
                ['status' => 'completed', 'processed_at' => now()->toIso8601String(), 'error' => null],
            ));
            $entry->saveQuietly();

            Logger::info('Entry updated with formatted content', [
                'entry_id' => $this->entryId,
                'bard_nodes' => count($bardContent),
            ]);

            // Step 5: Update processing log
            $processingTime = microtime(true) - $startTime;
            $log->markCompleted(
                $response->inputTokens,
                $response->outputTokens,
                $response->model,
                $processingTime,
            );

            // Step 6: Clean up temp file
            $this->cleanupFile();

            Logger::info('Sermon processing completed', [
                'entry_id' => $this->entryId,
                'processing_time' => round($processingTime, 2).'s',
                'total_tokens' => $response->totalTokens(),
            ]);
        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;
            $log->markFailed($e->getMessage());

            // Update entry sermon_source status
            try {
                $failedEntry = Entry::find($this->entryId);
                if ($failedEntry) {
                    $failedEntry->set('sermon_source', array_merge(
                        $failedEntry->get('sermon_source', []),
                        ['status' => 'failed', 'error' => $e->getMessage()],
                    ));
                    $failedEntry->saveQuietly();
                }
            } catch (\Exception $ignore) {
            }

            $this->cleanupFile();

            Logger::error('Sermon processing failed', [
                'entry_id' => $this->entryId,
                'error' => $e->getMessage(),
                'processing_time' => round($processingTime, 2).'s',
            ]);

            throw $e;
        }
    }

    protected function cleanupFile(): void
    {
        if (File::exists($this->filePath)) {
            File::delete($this->filePath);
            Logger::debug('Temp file cleaned up', ['path' => $this->filePath]);
        }
    }
}
