<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessingLog extends Model
{
    protected $table = 'sermon_processing_logs';

    protected $fillable = [
        'entry_id',
        'collection',
        'file_name',
        'status',
        'input_tokens',
        'output_tokens',
        'model',
        'stop_reason',
        'processing_time',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'processing_time' => 'float',
        ];
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markCompleted(int $inputTokens, int $outputTokens, string $model, float $processingTime, string $stopReason = 'end_turn'): void
    {
        $this->update([
            'status' => 'completed',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'model' => $model,
            'processing_time' => $processingTime,
            'stop_reason' => $stopReason,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
        ]);
    }

    public function getTotalTokensAttribute(): int
    {
        return ($this->input_tokens ?? 0) + ($this->output_tokens ?? 0);
    }
}
