<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OutboxStatus;
use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property OutboxStatus $status
 */
final class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use HasFactory;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'last_error_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'last_error_at' => 'datetime',
            'status' => OutboxStatus::class,
        ];
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        $maxAttempts = (int) config('outbox.max_attempts', 3);
        $retryInterval = (int) config('outbox.retry_interval_seconds', 300);

        return $query->whereIn('status', [OutboxStatus::Pending, OutboxStatus::Failed])
            ->where('attempts', '<', $maxAttempts)
            ->where(static function (Builder $query) use ($retryInterval): void {
                $query->whereNull('last_error_at')
                    ->orWhere('last_error_at', '<=', now()->subSeconds($retryInterval));
            });
    }

    public function markPublished(): void
    {
        $this->update(['status' => OutboxStatus::Published]);
    }

    public function markFailed(): void
    {
        $this->increment('attempts');
        $this->update([
            'status' => OutboxStatus::Failed,
            'last_error_at' => now(),
        ]);
    }
}
