<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TransferPublisherInterface;
use Carbon\Carbon;
use DateTimeInterface;

class TransferRetryPolicy
{
    public function __construct(
        private TransferPublisherInterface $publisher,
    ) {
    }

    public function shouldRetry(int $attempt): bool
    {
        return $attempt < $this->getRetryAttempts();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publishRetry(string $transferId, array $payload, int $attempt, string $reason): void
    {
        $nextAttempt = $attempt + 1;
        $scheduledAt = Carbon::now()->addSeconds($this->getRetryBackoffSeconds());

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
            'payload' => $payload,
        ];

        $this->publisher->publish($this->getRetryTopic(), $envelope, $transferId);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function publishDlq(array $body, string $reason): void
    {
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
