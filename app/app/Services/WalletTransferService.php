<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\Wallet;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;

/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
class WalletTransferService
{
    private const int LOCK_TIMEOUT_SECONDS = 10;

    private const int LOCK_TTL_SECONDS = 30;

    public function execute(
        int $userId,
        int $payerWalletId,
        int $payeeWalletId,
        int $amountCents,
        string $idempotencyKey,
    ): Transfer {
        $existingTransfer = $this->findExistingTransfer($userId, $idempotencyKey);

        if (!is_null($existingTransfer)) {
            return $existingTransfer;
        }

        if ($amountCents <= 0) {
            return $this->createFailedTransfer(
                $userId,
                $payerWalletId,
                $payeeWalletId,
                $amountCents,
                $idempotencyKey,
                FailureReason::InvalidAmount,
            );
        }

        $locks = $this->acquireWalletLocks($payerWalletId, $payeeWalletId);

        try {
            return $this->runInTransaction(
                $userId,
                $payerWalletId,
                $payeeWalletId,
                $amountCents,
                $idempotencyKey,
            );
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $existingTransfer = $this->findExistingTransfer($userId, $idempotencyKey);

                if (!is_null($existingTransfer)) {
                    return $existingTransfer;
                }
            }

            return $this->createFailedTransfer(
                $userId,
                $payerWalletId,
                $payeeWalletId,
                $amountCents,
                $idempotencyKey,
                FailureReason::IdempotencyConflict,
            );
        } catch (LockTimeoutException) {
            return $this->createFailedTransfer(
                $userId,
                $payerWalletId,
                $payeeWalletId,
                $amountCents,
                $idempotencyKey,
                FailureReason::WalletLocked,
            );
        } finally {
            $this->releaseLocks($locks);
        }
    }

    private function findExistingTransfer(int $userId, string $idempotencyKey): ?Transfer
    {
        return Transfer::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * @return array<int, Lock>
     */
    private function acquireWalletLocks(int $firstWalletId, int $secondWalletId): array
    {
        $walletIds = [$firstWalletId, $secondWalletId];
        sort($walletIds);

        $locks = [];

        foreach ($walletIds as $walletId) {
            try {
                $lock = Cache::lock("wallet:{$walletId}", self::LOCK_TTL_SECONDS);
                $lock->block(self::LOCK_TIMEOUT_SECONDS);
                $locks[] = $lock;
            } catch (LockTimeoutException $exception) {
                $this->releaseLocks($locks);

                throw $exception;
            } catch (\Throwable $exception) {
                Log::warning('Cache lock unavailable for wallet transfer; proceeding without lock.', [
                    'wallet_id' => $walletId,
                    'exception' => $exception->getMessage(),
                ]);

                break;
            }
        }

        return $locks;
    }

    /**
     * @param array<int, Lock> $locks
     */
    private function releaseLocks(array $locks): void
    {
        foreach ($locks as $lock) {
            try {
                $lock->release();
            } catch (\Throwable $exception) {
                Log::warning('Failed to release wallet lock.', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function runInTransaction(
        int $userId,
        int $payerWalletId,
        int $payeeWalletId,
        int $amountCents,
        string $idempotencyKey,
    ): Transfer {
        return DB::transaction(function () use (
            $userId,
            $payerWalletId,
            $payeeWalletId,
            $amountCents,
            $idempotencyKey,
        ): Transfer {
            $payerWallet = Wallet::lockForUpdate()->find($payerWalletId);
            $payeeWallet = Wallet::lockForUpdate()->find($payeeWalletId);

            if (is_null($payerWallet)) {
                return $this->createFailedTransfer(
                    $userId,
                    $payerWalletId,
                    $payeeWalletId,
                    $amountCents,
                    $idempotencyKey,
                    FailureReason::PayerNotFound,
                );
            }

            if (is_null($payeeWallet)) {
                return $this->createFailedTransfer(
                    $userId,
                    $payerWalletId,
                    $payeeWalletId,
                    $amountCents,
                    $idempotencyKey,
                    FailureReason::PayeeNotFound,
                );
            }

            if ($payerWallet->currency->value !== $payeeWallet->currency->value) {
                return $this->createFailedTransfer(
                    $userId,
                    $payerWalletId,
                    $payeeWalletId,
                    $amountCents,
                    $idempotencyKey,
                    FailureReason::CurrencyMismatch,
                );
            }

            $payerBalance = (int) $payerWallet->getRawOriginal('balance_cents');

            if ($payerBalance < $amountCents) {
                return $this->createFailedTransfer(
                    $userId,
                    $payerWalletId,
                    $payeeWalletId,
                    $amountCents,
                    $idempotencyKey,
                    FailureReason::InsufficientFunds,
                );
            }

            $payerWallet->balance_cents = $payerBalance - $amountCents;
            $payerWallet->save();

            $payeeBalance = (int) $payeeWallet->getRawOriginal('balance_cents');
            $payeeWallet->balance_cents = $payeeBalance + $amountCents;
            $payeeWallet->save();

            return Transfer::create([
                'user_id' => $userId,
                'payer_wallet_id' => $payerWalletId,
                'payee_wallet_id' => $payeeWalletId,
                'amount_cents' => $amountCents,
                'currency' => $payerWallet->currency->value,
                'idempotency_key' => $idempotencyKey,
                'status' => TransferStatus::Completed,
            ]);
        });
    }

    private function createFailedTransfer(
        int $userId,
        int $payerWalletId,
        int $payeeWalletId,
        int $amountCents,
        string $idempotencyKey,
        FailureReason $reason,
    ): Transfer {
        try {
            return Transfer::create([
                'user_id' => $userId,
                'payer_wallet_id' => $payerWalletId,
                'payee_wallet_id' => $payeeWalletId,
                'amount_cents' => $amountCents,
                'currency' => CurrencyType::BRA->value,
                'idempotency_key' => $idempotencyKey,
                'status' => TransferStatus::Failed,
                'failure_reason' => $reason,
            ]);
        } catch (QueryException $exception) {
            $existing = $this->findExistingTransfer($userId, $idempotencyKey);

            if (!is_null($existing)) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        if ($exception->getCode() !== '23000') {
            return false;
        }

        $previous = $exception->getPrevious();

        return $previous instanceof PDOException && $previous->getCode() === '23000';
    }
}
