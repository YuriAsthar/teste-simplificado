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
use App\Models\OutboxEvent;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;

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
            Log::info('Executing transfer without idempotency key.', [
                'payer_id' => $payerId,
                'payee_id' => $payeeId,
                'amount' => $amount,
            ]);

            return $this->executeWithoutKey($payerId, $payeeId, $amount);
        }

        Log::info('Executing transfer with idempotency key.', [
            'payer_id' => $payerId,
            'payee_id' => $payeeId,
            'amount' => $amount,
            'idempotency_key' => $idempotencyKey,
        ]);

        $requestHash = $this->idempotencyService->buildRequestHash($payerId, $payeeId, $amount);

        $acquisition = $this->idempotencyService->acquireOrResolveIdempotencyKey($idempotencyKey, $requestHash);

        if (!$acquisition['created'] && $acquisition['record']->status === IdempotencyKeyStatus::Processing) {
            throw new IdempotencyKeyInProgressException();
        }

        if (!$acquisition['created'] && $acquisition['record']->status === IdempotencyKeyStatus::Completed) {
            $transfer = $acquisition['record']->transfer;

            if (!is_null($transfer)) {
                return $transfer;
            }
        }

        return $this->executeWithKey($payerId, $payeeId, $amount, $idempotencyKey, $requestHash);
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
        string $requestHash,
    ): Transfer {
        if ($payerId === $payeeId) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::SamePayerAndPayee,
                $requestHash,
            );
        }

        if ($amount <= 0) {
            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::InvalidAmount,
                $requestHash,
            );
        }

        try {
            $transfer = $this->runValidationsAndTransaction($payerId, $payeeId, $amount, $idempotencyKey, $requestHash);
        } catch (AuthorizerRejectedException|TransientAuthorizerException $exception) {
            $this->idempotencyService->deleteProcessingIdempotencyKey($idempotencyKey);

            throw $exception;
        } catch (\Throwable $exception) {
            $this->idempotencyService->deleteProcessingIdempotencyKey($idempotencyKey);

            throw $exception;
        }

        $this->idempotencyService->finalizeIdempotencyKeyWithoutResponse($idempotencyKey, $requestHash, $transfer);

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
        ?string $requestHash = null,
    ): Transfer {
        $payer = $this->findActiveUser($payerId);
        $payee = $this->findActiveUser($payeeId);

        if (is_null($payer)) {
            Log::warning('Transfer validation failed: payer not found.', [
                'payer_id' => $payerId,
            ]);

            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::PayerNotFound,
                $requestHash,
            );
        }

        if (is_null($payee)) {
            Log::warning('Transfer validation failed: payee not found.', [
                'payee_id' => $payeeId,
            ]);

            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::PayeeNotFound,
                $requestHash,
            );
        }

        /** @var UserType $userType */
        $userType = $payer->type;

        if ($userType->isMerchant()) {
            Log::warning('Transfer validation failed: payer is merchant.', [
                'payer_id' => $payerId,
            ]);

            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::PayerIsMerchant,
                $requestHash,
            );
        }

        $payerWallet = $payer->wallet;
        $payeeWallet = $payee->wallet;

        if (is_null($payerWallet) || $payerWallet->trashed()) {
            Log::warning('Transfer validation failed: payer wallet inactive.', [
                'payer_id' => $payerId,
            ]);

            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::WalletInactive,
                $requestHash,
            );
        }

        if (is_null($payeeWallet) || $payeeWallet->trashed()) {
            Log::warning('Transfer validation failed: payee wallet inactive.', [
                'payee_id' => $payeeId,
            ]);

            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::WalletInactive,
                $requestHash,
            );
        }

        if ($payerWallet->currency->value !== $payeeWallet->currency->value) {
            Log::warning('Transfer validation failed: currency mismatch.', [
                'payer_id' => $payerId,
                'payee_id' => $payeeId,
            ]);

            return $this->createFailedTransfer(
                $payerId,
                $payeeId,
                $amount,
                $idempotencyKey,
                FailureReason::CurrencyMismatch,
                $requestHash,
            );
        }

        $authorizerResult = $this->authorizer->authorize();

        if ($authorizerResult === AuthorizerResult::Rejected) {
            Log::warning('Transfer validation failed: authorizer rejected.', [
                'payer_id' => $payerId,
                'payee_id' => $payeeId,
            ]);

            throw new AuthorizerRejectedException();
        }

        if ($authorizerResult === AuthorizerResult::Transient) {
            Log::warning('Transfer validation failed: authorizer transient error.', [
                'payer_id' => $payerId,
                'payee_id' => $payeeId,
            ]);

            throw new TransientAuthorizerException();
        }

        Log::info('Transfer validations passed; starting transaction.', [
            'payer_id' => $payerId,
            'payee_id' => $payeeId,
            'amount' => $amount,
        ]);

        return $this->runInTransaction($payer, $payee, $payerWallet, $payeeWallet, $amount, $idempotencyKey, $requestHash);
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
        ?string $requestHash = null,
    ): Transfer {
        return DB::transaction(function () use (
            $payer,
            $payee,
            $payerWallet,
            $payeeWallet,
            $amount,
            $idempotencyKey,
            $requestHash,
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
                Log::warning('Transfer failed: insufficient funds.', [
                    'payer_id' => $payer->id,
                    'payee_id' => $payee->id,
                    'amount' => $amount,
                    'balance' => $payerBalance,
                ]);

                return $this->createFailedTransfer(
                    $payer->id,
                    $payee->id,
                    $amount,
                    $idempotencyKey,
                    FailureReason::InsufficientFunds,
                    $requestHash,
                );
            }

            $lockedPayerWallet->balance = $payerBalance - $amount;
            $lockedPayerWallet->save();

            $payeeBalance = (int) $lockedPayeeWallet->getRawOriginal('balance');
            $lockedPayeeWallet->balance = $payeeBalance + $amount;
            $lockedPayeeWallet->save();

            Log::info('Transfer balances updated.', [
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'amount' => $amount,
            ]);

            $transfer = Transfer::create([
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'amount' => $amount,
                'currency' => $payerWallet->currency->value,
                'idempotency_key' => $idempotencyKey,
                'status' => TransferStatus::Completed,
            ]);

            OutboxEvent::create([
                'aggregate_type' => 'transfer',
                'aggregate_id' => $transfer->id,
                'event_type' => 'transfer.completed',
                'payload' => [
                    'transfer_id' => $transfer->id,
                    'payer_id' => $payer->id,
                    'payee_id' => $payee->id,
                    'amount' => $amount,
                    'currency' => $payerWallet->currency->value,
                    'occurred_at' => now()->toIso8601String(),
                ],
                'status' => 'Pending',
            ]);

            return $transfer;
        });
    }

    private function createFailedTransfer(
        int $payerId,
        int $payeeId,
        int $amount,
        ?string $idempotencyKey,
        FailureReason $reason,
        ?string $requestHash = null,
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
                $this->idempotencyService->finalizeIdempotencyKeyWithoutResponse(
                    $idempotencyKey,
                    $requestHash ?? $this->idempotencyService->buildRequestHash($payerId, $payeeId, $amount),
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
