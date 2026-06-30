<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTransferRequest;
use App\Models\Wallet;
use App\Services\WalletTransferService;
use Illuminate\Http\JsonResponse;

/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 * @SuppressWarnings("PHPMD.LongVariable")
 */
final class TransferController extends Controller
{
    public function __construct(
        private readonly WalletTransferService $service,
    ) {
    }

    public function __invoke(CreateTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var Wallet $payerWallet */
        $payerWallet = Wallet::findOrFail($validated['payer_wallet_id']);

        $authenticatedUser = $request->user();
        $userId = is_null($authenticatedUser)
            ? $payerWallet->user_id
            : $authenticatedUser->id;

        $transfer = $this->service->execute(
            $userId,
            $validated['payer_wallet_id'],
            $validated['payee_wallet_id'],
            $validated['amount_cents'],
            $validated['idempotency_key'],
        );

        return response()->json([
            'data' => [
                'id' => $transfer->id,
                'status' => $transfer->status->value,
                'failure_reason' => $transfer->failure_reason?->value,
            ],
        ], 201);
    }
}
