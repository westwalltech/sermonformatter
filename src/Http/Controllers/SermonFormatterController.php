<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use NewSong\SermonFormatter\Jobs\ProcessSermonDocument;
use NewSong\SermonFormatter\Models\ProcessingLog;
use NewSong\SermonFormatter\Services\ClaudeClient;
use NewSong\SermonFormatter\Services\FormattingSpecs;
use NewSong\SermonFormatter\Services\MarkdownToBard;
use NewSong\SermonFormatter\Support\Logger;
use Statamic\Facades\Entry;

class SermonFormatterController extends Controller
{
    public function dashboard()
    {
        return Inertia::render('sermon-formatter::Dashboard');
    }

    public function logs()
    {
        return Inertia::render('sermon-formatter::Logs');
    }

    public function specs()
    {
        return Inertia::render('sermon-formatter::Specs');
    }

    public function stats(): JsonResponse
    {
        $totalProcessed = ProcessingLog::completed()->count();
        $totalFailed = ProcessingLog::failed()->count();
        $totalPending = ProcessingLog::pending()->count() + ProcessingLog::processing()->count();

        $totalTokens = ProcessingLog::completed()
            ->selectRaw('SUM(input_tokens + output_tokens) as total')
            ->value('total') ?? 0;

        $avgProcessingTime = ProcessingLog::completed()
            ->avg('processing_time') ?? 0;

        $recentLogs = ProcessingLog::orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'entry_id' => $log->entry_id,
                'collection' => $log->collection,
                'file_name' => $log->file_name,
                'status' => $log->status,
                'tokens' => $log->total_tokens,
                'processing_time' => $log->processing_time ? round($log->processing_time, 1).'s' : null,
                'error' => $log->error,
                'created_at' => $log->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'total_processed' => $totalProcessed,
            'total_failed' => $totalFailed,
            'total_pending' => $totalPending,
            'total_tokens' => $totalTokens,
            'avg_processing_time' => round($avgProcessingTime, 1),
            'recent_logs' => $recentLogs,
        ]);
    }

    public function logsData(Request $request): JsonResponse
    {
        $query = ProcessingLog::query();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($collection = $request->get('collection')) {
            $query->where('collection', $collection);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('entry_id', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($logs);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.(config('sermon-formatter.processing.max_file_size', 10) * 1024),
            ],
            'entry_id' => 'required|string',
            'collection' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $allowedExtensions = config('sermon-formatter.processing.allowed_extensions', ['docx', 'rtf']);
        if (! in_array($extension, $allowedExtensions)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type. Allowed: '.implode(', ', $allowedExtensions),
            ], 422);
        }

        // Verify entry exists
        $entry = Entry::find($request->input('entry_id'));
        if (! $entry) {
            return response()->json([
                'success' => false,
                'message' => 'Entry not found.',
            ], 404);
        }

        // Derive collection from entry if not provided
        $collection = $request->input('collection');
        if (empty($collection) && method_exists($entry, 'collectionHandle')) {
            $collection = $entry->collectionHandle();
        }

        // Store file in temp location
        $uploadDir = storage_path('sermon-formatter/uploads');
        if (! File::isDirectory($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        $fileName = $file->getClientOriginalName();
        $storedName = time().'_'.$fileName;
        $file->move($uploadDir, $storedName);

        $filePath = $uploadDir.'/'.$storedName;

        // Create processing log
        $log = ProcessingLog::create([
            'entry_id' => $request->input('entry_id'),
            'collection' => $collection,
            'file_name' => $fileName,
            'status' => 'pending',
        ]);

        // Get target field from config or fieldtype config
        $targetField = $request->input('target_field', config('sermon-formatter.processing.target_field', 'notes'));

        // Update entry's sermon_source field BEFORE dispatching job
        // (important for sync queue where job runs immediately)
        $entry->set('sermon_source', [
            'status' => 'pending',
            'file_name' => $fileName,
            'processed_at' => null,
            'error' => null,
            'log_id' => $log->id,
        ]);
        $entry->saveQuietly();

        Logger::info('Sermon upload received', [
            'entry_id' => $request->input('entry_id'),
            'file' => $fileName,
            'log_id' => $log->id,
        ]);

        // Dispatch processing job AFTER saving pending status
        // Job will update status to completed/failed when done
        ProcessSermonDocument::dispatch(
            $request->input('entry_id'),
            $collection,
            $filePath,
            $fileName,
            $log->id,
            $targetField,
        );

        return response()->json([
            'success' => true,
            'message' => 'File uploaded and queued for processing.',
            'log_id' => $log->id,
            'status' => 'pending',
        ]);
    }

    public function status(string $entryId): JsonResponse
    {
        $log = ProcessingLog::where('entry_id', $entryId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $log) {
            return response()->json([
                'status' => null,
                'message' => 'No processing record found.',
            ]);
        }

        return response()->json([
            'status' => $log->status,
            'file_name' => $log->file_name,
            'error' => $log->error,
            'tokens' => $log->total_tokens,
            'processing_time' => $log->processing_time ? round($log->processing_time, 1) : null,
            'created_at' => $log->created_at?->toIso8601String(),
            'updated_at' => $log->updated_at?->toIso8601String(),
        ]);
    }

    public function reprocess(string $entryId): JsonResponse
    {
        $log = ProcessingLog::where('entry_id', $entryId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $log) {
            return response()->json([
                'success' => false,
                'message' => 'No previous processing record found.',
            ], 404);
        }

        // Check if the file still exists
        $filePath = storage_path('sermon-formatter/uploads/'.time().'_'.$log->file_name);

        // If original file is gone, we need a re-upload
        $entry = Entry::find($entryId);
        if (! $entry) {
            return response()->json([
                'success' => false,
                'message' => 'Entry not found.',
            ], 404);
        }

        // Create new log entry
        $newLog = ProcessingLog::create([
            'entry_id' => $entryId,
            'collection' => $log->collection,
            'file_name' => $log->file_name,
            'status' => 'pending',
        ]);

        // We need the original file - check if it exists
        $existingFile = $this->findUploadedFile($log->file_name);
        if (! $existingFile) {
            $newLog->markFailed('Original file not found. Please re-upload the document.');

            return response()->json([
                'success' => false,
                'message' => 'Original file not found. Please re-upload the document.',
            ], 404);
        }

        ProcessSermonDocument::dispatch(
            $entryId,
            $log->collection,
            $existingFile,
            $log->file_name,
            $newLog->id,
        );

        // Update entry status
        $entry->set('sermon_source', [
            'status' => 'pending',
            'file_name' => $log->file_name,
            'processed_at' => null,
            'error' => null,
            'log_id' => $newLog->id,
        ]);
        $entry->saveQuietly();

        Logger::info('Sermon reprocess queued', [
            'entry_id' => $entryId,
            'new_log_id' => $newLog->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Re-processing queued.',
            'log_id' => $newLog->id,
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $text = $request->input('text', '');

        if (empty(trim($text))) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter some sample text to test.',
            ], 422);
        }

        try {
            $specs = app(FormattingSpecs::class);
            $claude = app(ClaudeClient::class);
            $converter = app(MarkdownToBard::class);

            $response = $claude->send($specs->buildSystemPrompt(), $text);
            $bardContent = $converter->convert($response->content);

            return response()->json([
                'success' => true,
                'markdown' => $response->content,
                'bard_content' => $bardContent,
                'tokens' => [
                    'input' => $response->inputTokens,
                    'output' => $response->outputTokens,
                    'total' => $response->totalTokens(),
                ],
                'model' => $response->model,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkReprocess(Request $request): JsonResponse
    {
        $request->validate([
            'entry_ids' => 'required|array',
            'entry_ids.*' => 'string',
        ]);

        $dispatched = 0;
        $skipped = 0;

        foreach ($request->input('entry_ids') as $entryId) {
            $log = ProcessingLog::where('entry_id', $entryId)
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $log) {
                $skipped++;

                continue;
            }

            $file = $this->findUploadedFile($log->file_name);
            if (! $file) {
                $skipped++;

                continue;
            }

            $newLog = ProcessingLog::create([
                'entry_id' => $entryId,
                'collection' => $log->collection,
                'file_name' => $log->file_name,
                'status' => 'pending',
            ]);

            ProcessSermonDocument::dispatch(
                $entryId,
                $log->collection,
                $file,
                $log->file_name,
                $newLog->id,
            );

            $dispatched++;
        }

        return response()->json([
            'success' => true,
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ]);
    }

    public function specsContent(): JsonResponse
    {
        $specs = app(FormattingSpecs::class);

        return response()->json([
            'content' => $specs->get(),
            'is_default' => ! File::exists(storage_path('sermon-formatter/formatting-specs.md')),
        ]);
    }

    public function saveSpecs(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $specs = app(FormattingSpecs::class);

        if ($request->input('reset', false)) {
            $specs->reset();

            return response()->json([
                'success' => true,
                'message' => 'Formatting specs reset to defaults.',
                'content' => $specs->getDefault(),
            ]);
        }

        $specs->save($request->input('content'));

        return response()->json([
            'success' => true,
            'message' => 'Formatting specs saved.',
        ]);
    }

    protected function findUploadedFile(string $fileName): ?string
    {
        $uploadDir = storage_path('sermon-formatter/uploads');
        if (! File::isDirectory($uploadDir)) {
            return null;
        }

        // Look for the file (may have timestamp prefix)
        $files = File::glob($uploadDir.'/*_'.$fileName);
        if (! empty($files)) {
            return end($files); // Return the most recent one
        }

        // Also check for exact match
        $exactPath = $uploadDir.'/'.$fileName;
        if (File::exists($exactPath)) {
            return $exactPath;
        }

        return null;
    }
}
