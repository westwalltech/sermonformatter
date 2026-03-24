<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Console\Commands;

use Illuminate\Console\Command;
use NewSong\SermonFormatter\Models\ProcessingLog;
use NewSong\SermonFormatter\Support\Logger;

class RecoverStaleJobsCommand extends Command
{
    protected $signature = 'sermon-formatter:recover-stale
                            {--minutes=10 : Mark processing jobs older than this as failed}
                            {--dry-run : Preview which jobs would be recovered}';

    protected $description = 'Recover stale processing jobs that never completed';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = $this->option('dry-run');

        $staleJobs = ProcessingLog::processing()
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->get();

        if ($staleJobs->isEmpty()) {
            $this->info('No stale jobs found.');

            return self::SUCCESS;
        }

        $this->info("Found {$staleJobs->count()} stale job(s).");

        if ($dryRun) {
            $this->table(
                ['ID', 'Entry', 'File', 'Started'],
                $staleJobs->map(fn ($log) => [
                    $log->id,
                    $log->entry_id,
                    $log->file_name,
                    $log->updated_at?->diffForHumans(),
                ])
            );

            return self::SUCCESS;
        }

        foreach ($staleJobs as $log) {
            $log->markFailed("Processing timed out after {$minutes} minutes.");
            $this->warn("Marked as failed: {$log->entry_id} ({$log->file_name})");

            Logger::warning('Recovered stale processing job', [
                'log_id' => $log->id,
                'entry_id' => $log->entry_id,
            ]);
        }

        $this->info("Recovered {$staleJobs->count()} stale job(s).");

        return self::SUCCESS;
    }
}
