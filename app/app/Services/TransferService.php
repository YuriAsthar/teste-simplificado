<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TransferPublisherInterface;
use App\Jobs\SendNotificationJob;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final readonly class TransferService
{
    private const int CACHE_TTL_SECONDS = 60;

    public function __construct(
        private Repository $cache,
        private Dispatcher $dispatcher,
        private TransferPublisherInterface $publisher,
        private TransferMessageBuilder $messageBuilder,
        private User $user,
        private LoggerInterface $logger,
    ) {
    }

    public function getCachedUser(int $userId): ?User
    {
        $found = $this->cache->remember("user:{$userId}", self::CACHE_TTL_SECONDS, fn () => $this->user->find($userId));

        return $found instanceof User ? $found : null;
    }

    /**
     * @return array{transfer_id: string, status: string}
     */
    public function authorizeAndExecuteTransfer(int $payerId, int $payeeId, int $amountCents): array
    {
        $payer = $this->getCachedUser($payerId);
        $payee = $this->getCachedUser($payeeId);

        if (is_null($payer) || is_null($payee)) {
            throw new InvalidArgumentException('Payer and payee must exist.');
        }

        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $transferId = $this->generateTransferId();
        $payload = [
            'transfer_id' => $transferId,
            'payer_id' => $payerId,
            'payee_id' => $payeeId,
            'amount_cents' => $amountCents,
            'occurred_at' => now()->toIso8601String(),
        ];

        $message = $this->messageBuilder->build($payload);
        $this->publisher->publish($message['topic'], $message['envelope'], $message['key']);

        $this->dispatcher->dispatch(
            new SendNotificationJob($payerId, $payeeId, $amountCents, $transferId)
        )->onConnection('rabbitmq');

        $this->invalidateUserCache($payerId);
        $this->invalidateUserCache($payeeId);

        $this->logger->info('Transfer authorized', [
            'transfer_id' => $transferId,
            'payer_id' => $payerId,
            'payee_id' => $payeeId,
            'amount_cents' => $amountCents,
        ]);

        return [
            'transfer_id' => $transferId,
            'status' => 'authorized',
        ];
    }

    private function invalidateUserCache(int $userId): void
    {
        $this->cache->forget("user:{$userId}");
    }

    private function generateTransferId(): string
    {
        return 'txn_' . bin2hex(random_bytes(8));
    }
}
