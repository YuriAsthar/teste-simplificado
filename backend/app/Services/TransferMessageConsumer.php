<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TransferMessagePayload;
use Illuminate\Support\Facades\Log;
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
    public function consume(array $message, bool $dryRun = false): void
    {
        $payload = $message['payload'] ?? null;
        $transferId = $this->extractTransferId($payload);

        Log::info('Processing transfer message.', [
            'transfer_id' => $transferId,
        ]);

        if (is_null($transferId)) {
            Log::warning('Transfer message rejected: missing transfer_id.', [
                'payload' => is_array($payload) ? array_diff_key($payload, ['transfer_id' => true]) : null,
            ]);

            if ($dryRun) {
                Log::info('[DRY-RUN] DLQ publish skipped for missing transfer_id.');

                return;
            }

            $this->retryPolicy->publishDlq($message, 'missing transfer_id');

            return;
        }

        $payloadDto = TransferMessagePayload::fromArray($payload);

        try {
            $this->processor->process($payloadDto, $dryRun);
            Log::info('Transfer processed successfully.', [
                'transfer_id' => $transferId,
            ]);
        } catch (Throwable $exception) {
            $attempt = $this->extractAttempt($message);

            if ($dryRun) {
                Log::warning('[DRY-RUN] Transfer processing failed; retry/DLQ publish skipped.', [
                    'transfer_id' => $transferId,
                    'attempt' => $attempt,
                    'exception' => $exception->getMessage(),
                ]);

                return;
            }

            if ($this->retryPolicy->shouldRetry($attempt)) {
                Log::warning('Transfer processing failed; retry will be published.', [
                    'transfer_id' => $transferId,
                    'attempt' => $attempt,
                    'exception' => $exception->getMessage(),
                ]);
                $this->retryPolicy->publishRetry($payloadDto, $attempt, $exception->getMessage());

                return;
            }

            Log::error('Transfer processing failed after max retries; sending to DLQ.', [
                'transfer_id' => $transferId,
                'attempt' => $attempt,
                'exception' => $exception->getMessage(),
            ]);
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

        if (is_int($transferId) && $transferId > 0) {
            return (string) $transferId;
        }

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
