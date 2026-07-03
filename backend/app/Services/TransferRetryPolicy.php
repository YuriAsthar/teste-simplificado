<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TransferPublisherInterface;
use App\DTOs\TransferMessagePayload;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransferRetryPolicy
{
    public function __construct(
        private TransferPublisherInterface $publisher,
    ) {
    }

    public function shouldRetry(int $attempt): bool
    {
        $maxRetries = $this->getRetryAttempts();
        $willRetry = $attempt < $maxRetries;

        if ($willRetry) {
            Log::info('Retry attempt incremented.', [
                'current_attempt' => $attempt,
                'max_retries' => $maxRetries,
            ]);

            return true;
        }

        Log::warning('Max retries reached.', [
            'current_attempt' => $attempt,
            'max_retries' => $maxRetries,
        ]);

        return false;
    }

    public function publishRetry(TransferMessagePayload $payload, int $attempt, string $reason): void
    {
        $nextAttempt = $attempt + 1;
        $scheduledAt = Carbon::now()->addSeconds($this->getRetryBackoffSeconds());
        $transferId = (string) $payload->transferId;

        Log::info('Publishing transfer retry message.', [
            'transfer_id' => $transferId,
            'next_attempt' => $nextAttempt,
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'reason' => $reason,
        ]);

        $envelope = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.retry',
                'occurred_at' => $this->formatNow(),
                'retry' => [
                    'attempt' => $nextAttempt,
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                ],
                'reason' => $reason,
            ],
            'payload' => $payload->toArray(),
        ];

        $this->publisher->publish($this->getRetryTopic(), $envelope, $transferId);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function publishDlq(array $body, string $reason): void
    {
        $transferId = is_array($body['payload'] ?? null)
            ? ($body['payload']['transfer_id'] ?? null)
            : null;

        Log::error('Publishing transfer message to DLQ.', [
            'transfer_id' => is_int($transferId) || is_string($transferId) ? (string) $transferId : null,
            'reason' => $reason,
        ]);

        $envelope = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.failed',
                'occurred_at' => $this->formatNow(),
                'reason' => $reason,
            ],
            'payload' => $body,
        ];

        $this->publisher->publish($this->getDlqTopic(), $envelope);
    }

    private function getRetryAttempts(): int
    {
        return (int) config('kafka.retry_attempts', 3);
    }

    private function getRetryBackoffSeconds(): int
    {
        return (int) config('kafka.retry_backoff_seconds', 60);
    }

    private function getRetryTopic(): string
    {
        return (string) config('kafka.topic_retry', 'wallet.transfer.retry');
    }

    private function getDlqTopic(): string
    {
        return (string) config('kafka.topic_dlq', 'wallet.transfer.dlq');
    }

    private function formatNow(): string
    {
        return Carbon::now()->toIso8601String();
    }
}
