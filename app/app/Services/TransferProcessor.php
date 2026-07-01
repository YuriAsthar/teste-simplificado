<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Services\DryRun\DryRunContext;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use Throwable;

class TransferProcessor
{
    private const string IDEMPOTENCY_PREFIX = 'kafka:transfer:';

    public function __construct(
        private Dispatcher $dispatcher,
        private Repository $cache,
        private DryRunContext $context,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function process(array $payload): void
    {
        $transferId = $this->extractTransferId($payload);

        if ($transferId === null) {
            throw new InvalidArgumentException('missing transfer_id');
        }

        if ($this->isDuplicate($transferId)) {
            return;
        }

        $payerId = (int) ($payload['payer_id'] ?? 0);
        $payeeId = (int) ($payload['payee_id'] ?? 0);
        $amountCents = (int) ($payload['amount'] ?? 0);

        $numericTransferId = is_numeric($transferId) ? (int) $transferId : 0;

        if ($this->context->isEnabled()) {
            $this->context->record('rabbitmq.dispatch', [
                'payer_id' => $payerId,
                'payee_id' => $payeeId,
                'amount' => $amountCents,
                'transfer_id' => $transferId,
            ]);
            $this->context->record('idempotency.skip', [
                'transfer_id' => $transferId,
            ]);

            return;
        }

        $this->dispatcher->dispatch(
            new SendNotificationJob($numericTransferId)
        )->onConnection('rabbitmq');

        $this->markProcessed($transferId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractTransferId(array $payload): ?string
    {
        $transferId = $payload['transfer_id'] ?? null;

        return is_string($transferId) && $transferId !== '' ? $transferId : null;
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
