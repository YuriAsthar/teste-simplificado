<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\DryRun\DryRunContext;
use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;
use Throwable;

class TransferRetryMessageConsumer
{
    public function __construct(
        private KafkaTransferProcessor $processor,
        private TransferRetryPolicy $retryPolicy,
        private DryRunContext $context,
    ) {
    }

    /**
     * @param array<string, mixed> $message
     */
    public function consume(array $message): void
    {
        $retry = $this->extractRetryMeta($message);

        if ($retry === null) {
            return;
        }

        $scheduledAt = $this->parseScheduledAt($retry['scheduled_at']);

        if (!$scheduledAt instanceof \DateTimeInterface) {
            $this->retryPolicy->publishDlq($message, 'invalid retry scheduled_at');

            return;
        }

        if ($this->context->isEnabled()) {
            $this->context->record('retry.wait_skipped', [
                'scheduled_at' => $scheduledAt->format(DateTimeInterface::ATOM),
            ]);

            $this->continueConsumption($message, $retry);

            return;
        }

        $this->sleepUntilDue($scheduledAt);
        $this->continueConsumption($message, $retry);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $retry
     */
    private function continueConsumption(array $message, array $retry): void
    {
        $transferId = $this->extractTransferId($message);

        if ($transferId === null) {
            $this->retryPolicy->publishDlq($message, 'missing transfer_id');

            return;
        }

        $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];

        $this->reprocess($transferId, $payload, $retry['attempt'], $message);
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|null
     */
    private function extractRetryMeta(array $message): ?array
    {
        $meta = $message['meta'] ?? null;

        if (!is_array($meta)) {
            $this->retryPolicy->publishDlq($message, 'missing meta');

            return null;
        }

        $retry = $meta['retry'] ?? null;

        if (!is_array($retry) || !isset($retry['attempt']) || !isset($retry['scheduled_at'])) {
            $this->retryPolicy->publishDlq($message, 'missing retry metadata');

            return null;
        }

        return $retry;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractTransferId(array $message): ?string
    {
        $payload = $message['payload'] ?? null;

        if (!is_array($payload)) {
            return null;
        }

        $transferId = $payload['transfer_id'] ?? null;

        return is_string($transferId) && $transferId !== '' ? $transferId : null;
    }

    private function parseScheduledAt(mixed $scheduledAt): ?DateTimeInterface
    {
        if (!is_string($scheduledAt) || $scheduledAt === '') {
            return null;
        }

        try {
            return new Carbon($scheduledAt);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    protected function sleepUntilDue(DateTimeInterface $scheduledAt): void
    {
        $seconds = max(0, $scheduledAt->getTimestamp() - Carbon::now()->getTimestamp());

        if ($seconds > 0) {
            sleep($seconds);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $message
     */
    private function reprocess(string $transferId, array $payload, mixed $attempt, array $message): void
    {
        try {
            $this->processor->process($payload);
        } catch (Throwable $exception) {
            $attemptNumber = (int) $attempt;

            if ($this->retryPolicy->shouldRetry($attemptNumber)) {
                $this->retryPolicy->publishRetry($transferId, $payload, $attemptNumber, $exception->getMessage());

                return;
            }

            $this->retryPolicy->publishDlq($message, $exception->getMessage());
        }
    }
}
