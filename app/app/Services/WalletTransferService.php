<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Enums\UserType;
use App\Models\IdempotencyKey;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final readonly class WalletTransferService
{
    public function __construct(
        private AuthorizerClient $authorizer,
        private Dispatcher $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(
        int $payerId,
        int $payeeId,
        int $amountCents,
        ?string $idempotencyKey = null,
    ): Transfer {
        if ($payerId === $payeeId) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                null,
                FailureReason::SamePayerAndPayee,
            );
        }

        if ($amountCents <= 0) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                null,
                FailureReason::InvalidAmount,
            );
        }

        $key = $this->resolveIdempotencyKey($payerId, $payeeId, $amountCents, $idempotencyKey);

        $existingTransfer = $this->findExistingTransfer($key);

        if (!is_null($existingTransfer)) {
            return $existingTransfer;
        }

        $payer = $this->findActiveUser($payerId);
        $payee = $this->findActiveUser($payeeId);

        if (is_null($payer)) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                $key,
                FailureReason::PayerNotFound,
            );
        }

        if (is_null($payee)) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                $key,
                FailureReason::PayeeNotFound,
            );
        }

        /** @var UserType $userType */
        $userType = $payer->type;

        if ($userType->isMerchant()) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                $key,
                FailureReason::PayerIsMerchant,
            );
        }

        $payerWallet = $payer->wallet;
        $payeeWallet = $payee->wallet;

        if (is_null($payerWallet) || $payerWallet->trashed()) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                $key,
                FailureReason::WalletInactive,
            );
        }

        if (is_null($payeeWallet) || $payeeWallet->trashed()) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                $key,
                FailureReason::WalletInactive,
            );
        }

        if ($payerWallet->currency->value !== $payeeWallet->currency->value) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                $key,
                FailureReason::CurrencyMismatch,
            );
        }

        if (!$this->authorizer->authorize()) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amountCents,
                $key,
                FailureReason::AuthorizerRejected,
            );
        }

        return $this->runInTransaction($payer, $payee, $payerWallet, $payeeWallet, $amountCents, $key);
    }

    private function resolveIdempotencyKey(
        int $payerId,
        int $payeeId,
        int $amountCents,
        ?string $providedKey,
    ): string {
        if (!empty($providedKey)) {
            return $providedKey;
        }

        return hash('sha256', "transfer:{$payerId}:{$payeeId}:{$amountCents}");
    }

    private function findExistingTransfer(string $idempotencyKey): ?Transfer
    {
        $keyRecord = IdempotencyKey::query()
            ->where('key', $idempotencyKey)
            ->first();

        return $keyRecord?->transfer;
    }

    private function findActiveUser(int $userId): ?User
    {
        try {
            return User::query()
                ->whereNull('deleted_at')
                ->with('wallet')
                ->findOrFail($userId);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function runInTransaction(
        User $payer,
        User $payee,
        Wallet $payerWallet,
        Wallet $payeeWallet,
        int $amountCents,
        string $idempotencyKey,
    ): Transfer {
        return DB::transaction(function () use (
            $payer,
            $payee,
            $payerWallet,
            $payeeWallet,
            $amountCents,
            $idempotencyKey,
        ): Transfer {
            $firstWalletId = min($payerWallet->id, $payeeWallet->id);
            $secondWalletId = max($payerWallet->id, $payeeWallet->id);

            $lockedFirst = Wallet::lockForUpdate()->find($firstWalletId);
            $lockedSecond = Wallet::lockForUpdate()->find($secondWalletId);

            if (is_null($lockedFirst) || is_null($lockedSecond)) {
                throw new RuntimeException('Wallet disappeared during transfer.');
            }

            $lockedPayerWallet = $lockedFirst->id === $payerWallet->id ? $lockedFirst : $lockedSecond;
            $lockedPayeeWallet = $lockedFirst->id === $payeeWallet->id ? $lockedFirst : $lockedSecond;

            $payerBalance = (int) $lockedPayerWallet->getRawOriginal('balance_cents');

            if ($payerBalance < $amountCents) {
                $transfer = $this->createFailedTransfer(
                    $payer->id,
                    $payee->id,
                    $amountCents,
                    $idempotencyKey,
                    FailureReason::InsufficientFunds,
                );

                $this->upsertIdempotencyKey($idempotencyKey, $transfer);

                return $transfer;
            }

            $lockedPayerWallet->balance_cents = $payerBalance - $amountCents;
            $lockedPayerWallet->save();

            $payeeBalance = (int) $lockedPayeeWallet->getRawOriginal('balance_cents');
            $lockedPayeeWallet->balance_cents = $payeeBalance + $amountCents;
            $lockedPayeeWallet->save();

            $transfer = Transfer::create([
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'amount_cents' => $amountCents,
                'currency' => $payerWallet->currency->value,
                'idempotency_key' => $idempotencyKey,
                'status' => TransferStatus::Completed,
            ]);

            $this->upsertIdempotencyKey($idempotencyKey, $transfer);

            $this->dispatchNotification($transfer);

            return $transfer;
        });
    }

    private function dispatchNotification(Transfer $transfer): void
    {
        try {
            $this->dispatcher->dispatch(
                new \App\Jobs\SendTransferNotificationJob($transfer->id),
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Queue dispatch unavailable; notification skipped.', [
                'transfer_id' => $transfer->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function upsertIdempotencyKey(string $idempotencyKey, Transfer $transfer): void
    {
        IdempotencyKey::updateOrCreate(
            ['key' => $idempotencyKey],
            ['transfer_id' => $transfer->id],
        );
    }

    private function createFailedTransfer(
        int $payerId,
        int $payeeId,
        int $amountCents,
        ?string $idempotencyKey,
        FailureReason $reason,
    ): Transfer {
        $transfer = Transfer::create([
            'payer_id' => $payerId,
            'payee_id' => $payeeId,
            'amount_cents' => $amountCents,
            'currency' => CurrencyType::BRA->value,
            'idempotency_key' => $idempotencyKey,
            'status' => TransferStatus::Failed,
            'failure_reason' => $reason,
        ]);

        if (!empty($idempotencyKey)) {
            try {
                $this->upsertIdempotencyKey($idempotencyKey, $transfer);
            } catch (\Throwable $exception) {
                $this->logger->warning('Failed to record idempotency key for failed transfer.', [
                    'transfer_id' => $transfer->id,
                    'idempotency_key' => $idempotencyKey,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $transfer;
    }
}
