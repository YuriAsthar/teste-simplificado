<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

class TransferMessageConsumer
{
    public function __construct(
        private KafkaTransferProcessor $processor,
        private TransferRetryPolicy $retryPolicy,
    ) {
    }

    /**
     * @param array<string, mixed> $message
     */
    public function consume(array $message): void
    {
        $payload = $message['payload'] ?? null;
        $transferId = $this->extractTransferId($payload);

        if ($transferId === null) {
            $this->retryPolicy->publishDlq($message, 'missing transfer_id');

            return;
        }

        try {
            $this->processor->process($payload);
        } catch (Throwable $exception) {
            $attempt = $this->extractAttempt($message);

            if ($this->retryPolicy->shouldRetry($attempt)) {
                $this->retryPolicy->publishRetry($transferId, $payload, $attempt, $exception->getMessage());

                return;
            }

            $this->retryPolicy->publishDlq($message, $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed>|mixed $payload
     */
    private function extractTransferId(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $transferId = $payload['transfer_id'] ?? null;

        return is_string($transferId) && $transferId !== '' ? $transferId : null;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractAttempt(array $message): int
    {
        $meta = $message['meta'] ?? [];

        if (!is_array($meta)) {
            return 0;
        }

        $retry = $meta['retry'] ?? [];

        if (!is_array($retry)) {
            return 0;
        }

        $attempt = $retry['attempt'] ?? 0;

        return is_int($attempt) ? $attempt : (int) $attempt;
    }
}
