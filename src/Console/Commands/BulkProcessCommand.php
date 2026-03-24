<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Console\Commands;

use Illuminate\Console\Command;
use NewSong\SermonFormatter\Jobs\ProcessSermonDocument;
use NewSong\SermonFormatter\Models\ProcessingLog;
use NewSong\SermonFormatter\Support\FileLocator;
use NewSong\SermonFormatter\Support\Logger;
use Statamic\Facades\Entry;

class BulkProcessCommand extends Command
{
    protected $signature = 'sermon-formatter:bulk-process
                            {collection? : The collection to process}
                            {--status=failed : Only re-process entries with this status (failed, completed, all)}
                            {--dry-run : Preview which entries would be processed}
                            {--limit=50 : Maximum number of entries to process}';

    protected $description = 'Bulk re-process sermon entries through the formatter';

    public function handle(): int
    {
        $collection = $this->argument('collection');
        $status = $this->option('status');
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $collections = $collection
            ? [$collection]
            : config('sermon-formatter.processing.collections', ['messages', 'nss_messages']);

        $query = ProcessingLog::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $query->whereIn('collection', $collections)
            ->limit($limit)
            ->orderBy('created_at', 'desc');

        $logs = $query->get();

        if ($logs->isEmpty()) {
            $this->info('No entries found matching criteria.');

            return self::SUCCESS;
        }

        $this->info("Found {$logs->count()} entries to process.");

        if ($dryRun) {
            $this->table(
                ['ID', 'Entry', 'Collection', 'File', 'Status', 'Last Processed'],
                $logs->map(fn ($log) => [
                    $log->id,
                    $log->entry_id,
                    $log->collection,
                    $log->file_name,
                    $log->status,
                    $log->updated_at?->diffForHumans(),
                ])
            );
            $this->info('Dry run complete. No entries were processed.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Process {$logs->count()} entries?")) {
            return self::SUCCESS;
        }

        $processed = 0;
        $skipped = 0;

        foreach ($logs as $log) {
            $entry = Entry::find($log->entry_id);
            if (! $entry) {
                $this->warn("Entry {$log->entry_id} not found, skipping.");
                $skipped++;

                continue;
            }

            // Check if the temp file still exists
            $filePath = FileLocator::findUploadedFile($log->file_name);
            if (! $filePath) {
                $this->warn("File {$log->file_name} not found for entry {$log->entry_id}, skipping.");
                $skipped++;

                continue;
            }

            // Re-dispatch the job
            $log->update(['status' => 'pending', 'error' => null]);
            ProcessSermonDocument::dispatch(
                $log->entry_id,
                $log->collection,
                $filePath,
                $log->file_name,
                $log->id
            );

            $processed++;
            $this->info("Dispatched: {$log->entry_id} ({$log->file_name})");
        }

        $this->newLine();
        $this->info("Dispatched {$processed} jobs. Skipped {$skipped}.");

        Logger::info('Bulk process completed', [
            'processed' => $processed,
            'skipped' => $skipped,
        ]);

        return self::SUCCESS;
    }
}
