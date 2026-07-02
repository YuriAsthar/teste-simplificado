<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\IdempotencyKeyStatus;
use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

final class CleanupStaleIdempotencyKeysCommand extends Command
{
    protected $signature = 'idempotency:cleanup-stale-keys';

    protected $description = 'Delete idempotency keys stuck in the processing state longer than the configured TTL';

    public function handle(): int
    {
        $ttlSeconds = (int) config('transfer.idempotency_processing_ttl_seconds', 300);
        $threshold = now()->subSeconds($ttlSeconds);

        $deleted = IdempotencyKey::query()
            ->where('status', IdempotencyKeyStatus::Processing->value)
            ->where('updated_at', '<', $threshold)
            ->delete();

        $this->info("Deleted {$deleted} stale idempotency key(s).");

        return self::SUCCESS;
    }
}
