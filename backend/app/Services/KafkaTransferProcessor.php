<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TransferMessagePayload;
use App\Enums\TransferStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Transfer;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Throwable;

class KafkaTransferProcessor
{
    private const string IDEMPOTENCY_PREFIX = 'kafka:transfer:';

    public function __construct(
        private Dispatcher $dispatcher,
        private Repository $cache,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function process(TransferMessagePayload $payload, bool $dryRun = false): void
    {
        $transferId = (string) $payload->transferId;

        if ($this->isDuplicate($transferId)) {
            return;
        }

        $payerId = $payload->payerId ?? 0;
        $payeeId = $payload->payeeId ?? 0;
        $amount = $payload->amount ?? '0';
        $numericTransferId = $payload->transferId;

        if ($dryRun) {
            Log::info('[DRY-RUN] RabbitMQ dispatch skipped.', [
                'payer_id' => $payerId,
                'payee_id' => $payeeId,
                'amount' => $amount,
                'transfer_id' => $numericTransferId,
            ]);
            Log::info('[DRY-RUN] Idempotency write skipped.', [
                'transfer_id' => $numericTransferId,
            ]);

            return;
        }

        $transfer = Transfer::query()->find($numericTransferId);

        if (is_null($transfer) || $transfer->status !== TransferStatus::Completed) {
            Log::warning('Kafka transfer event ignored; transfer missing or not completed.', [
                'transfer_id' => $numericTransferId,
                'status' => $transfer?->status?->value,
            ]);
            $this->markProcessed($transferId);

            return;
        }

        $this->dispatcher->dispatch(new SendNotificationJob($numericTransferId))->onConnection('rabbitmq');

        $this->markProcessed($transferId);
    }

    private function isDuplicate(string $transferId): bool
    {
        return $this->cache->has(self::IDEMPOTENCY_PREFIX . $transferId);
    }

    private function markProcessed(string $transferId): void
    {
        $this->cache->put(
            self::IDEMPOTENCY_PREFIX . $transferId,
            true,
            $this->getIdempotencyTtl()
        );
    }

    private function getIdempotencyTtl(): int
    {
        return (int) config('kafka.idempotency_ttl', 3600);
    }
}
