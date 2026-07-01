<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuthorizerResult;
use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\IdempotencyKeyStatus;
use App\Enums\TransferStatus;
use App\Enums\UserType;
use App\Exceptions\AuthorizerRejectedException;
use App\Exceptions\IdempotencyKeyInProgressException;
use App\Exceptions\TransientAuthorizerException;
use App\Jobs\SendNotificationJob;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final readonly class WalletTransferService
{
    public function __construct(
        private AuthorizerClient $authorizer,
        private IdempotencyKeyService $idempotencyService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AuthorizerRejectedException
     * @throws TransientAuthorizerException
     */
    public function execute(
        int $payerId,
        int $payeeId,
        int $amount,
        string $idempotencyKey,
    ): Transfer {
        if ($idempotencyKey === '') {
            return $this->executeWithoutKey($payerId, $payeeId, $amount);
        }

        $fingerprint = $this->idempotencyService->buildFingerprint($payerId, $payeeId, $amount);

        $acquisition = $this->idempotencyService->acquireOrResolveIdempotencyKey($idempotencyKey, $fingerprint);

        if (!$acquisition['created'] && $acquisition['record']->status === IdempotencyKeyStatus::Processing) {
            throw new IdempotencyKeyInProgressException();
        }

        if (!$acquisition['created'] && $acquisition['record']->status === IdempotencyKeyStatus::Completed) {
            $transfer = $acquisition['record']->transfer;

            if (!is_null($transfer)) {
                return $transfer;
            }
        }

        return $this->executeWithKey($payerId, $payeeId, $amount, $idempotencyKey, $fingerprint);
    }

    private function executeWithoutKey(
        int $payerId,
        int $payeeId,
        int $amount,
    ): Transfer {
        if ($payerId === $payeeId) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                null,
                FailureReason::SamePayerAndPayee,
            );
        }

        if ($amount <= 0) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                null,
                FailureReason::InvalidAmount,
            );
        }

        return $this->runValidationsAndTransaction($payerId, $payeeId, $amount, null);
    }

    /**
     * @throws AuthorizerRejectedException
     * @throws TransientAuthorizerException
     */
    private function executeWithKey(
        int $payerId,
        int $payeeId,
        int $amount,
        string $idempotencyKey,
        string $fingerprint,
    ): Transfer {
        if ($payerId === $payeeId) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::SamePayerAndPayee,
                $fingerprint,
            );
        }

        if ($amount <= 0) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::InvalidAmount,
                $fingerprint,
            );
        }

        try {
            $transfer = $this->runValidationsAndTransaction($payerId, $payeeId, $amount, $idempotencyKey, $fingerprint);
        } catch (AuthorizerRejectedException|TransientAuthorizerException $exception) {
            $this->idempotencyService->deleteProcessingIdempotencyKey($idempotencyKey);

            throw $exception;
        } catch (\Throwable $exception) {
            $this->idempotencyService->deleteProcessingIdempotencyKey($idempotencyKey);

            throw $exception;
        }

        $this->idempotencyService->finalizeIdempotencyKey($idempotencyKey, $fingerprint, $transfer);

        return $transfer;
    }

    /**
     * @throws AuthorizerRejectedException
     * @throws TransientAuthorizerException
     */
    private function runValidationsAndTransaction(
        int $payerId,
        int $payeeId,
        int $amount,
        ?string $idempotencyKey,
        ?string $fingerprint = null,
    ): Transfer {
        $payer = $this->findActiveUser($payerId);
        $payee = $this->findActiveUser($payeeId);

        if (is_null($payer)) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::PayerNotFound,
                $fingerprint,
            );
        }

        if (is_null($payee)) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::PayeeNotFound,
                $fingerprint,
            );
        }

        /** @var UserType $userType */
        $userType = $payer->type;

        if ($userType->isMerchant()) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::PayerIsMerchant,
                $fingerprint,
            );
        }

        $payerWallet = $payer->wallet;
        $payeeWallet = $payee->wallet;

        if (is_null($payerWallet) || $payerWallet->trashed()) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::WalletInactive,
                $fingerprint,
            );
        }

        if (is_null($payeeWallet) || $payeeWallet->trashed()) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::WalletInactive,
                $fingerprint,
            );
        }

        if ($payerWallet->currency->value !== $payeeWallet->currency->value) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::CurrencyMismatch,
                $fingerprint,
            );
        }

        $authorizerResult = $this->authorizer->authorize();

        if ($authorizerResult === AuthorizerResult::Rejected) {
            throw new AuthorizerRejectedException();
        }

        if ($authorizerResult === AuthorizerResult::Transient) {
            throw new TransientAuthorizerException();
        }

        return $this->runInTransaction($payer, $payee, $payerWallet, $payeeWallet, $amount, $idempotencyKey, $fingerprint);
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
        int $amount,
        ?string $idempotencyKey,
        ?string $fingerprint = null,
    ): Transfer {
        return DB::transaction(function () use (
            $payer,
            $payee,
            $payerWallet,
            $payeeWallet,
            $amount,
            $idempotencyKey,
            $fingerprint,
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

            $payerBalance = (int) $lockedPayerWallet->getRawOriginal('balance');

            if ($payerBalance < $amount) {
                return $this->createFailedTransfer(
                    $payer->id,
                    $payee->id,
                    $amount,
                    $idempotencyKey,
                    FailureReason::InsufficientFunds,
                    $fingerprint,
                );
            }

            $lockedPayerWallet->balance = $payerBalance - $amount;
            $lockedPayerWallet->save();

            $payeeBalance = (int) $lockedPayeeWallet->getRawOriginal('balance');
            $lockedPayeeWallet->balance = $payeeBalance + $amount;
            $lockedPayeeWallet->save();

            $transfer = Transfer::create([
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'amount' => $amount,
                'currency' => $payerWallet->currency->value,
                'idempotency_key' => $idempotencyKey,
                'status' => TransferStatus::Completed,
            ]);

            DB::afterCommit(function () use ($transfer): void {
                try {
                    SendNotificationJob::dispatch($transfer->id);
                } catch (\Throwable $exception) {
                    $this->logger->warning('Queue dispatch unavailable; notification skipped.', [
                        'transfer_id' => $transfer->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            });

            return $transfer;
        });
    }

    private function createFailedTransfer(
        int $payerId,
        int $payeeId,
        int $amount,
        ?string $idempotencyKey,
        FailureReason $reason,
        ?string $fingerprint = null,
    ): Transfer {
        $transfer = Transfer::create([
            'payer_id' => $payerId,
            'payee_id' => $payeeId,
            'amount' => $amount,
            'currency' => CurrencyType::BRA->value,
            'idempotency_key' => $idempotencyKey,
            'status' => TransferStatus::Failed,
            'failure_reason' => $reason,
        ]);

        if (!is_null($idempotencyKey) && $idempotencyKey !== '') {
            try {
                $this->idempotencyService->finalizeIdempotencyKey(
                    $idempotencyKey,
                    $fingerprint ?? $this->idempotencyService->buildFingerprint($payerId, $payeeId, $amount),
                    $transfer,
                );
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
