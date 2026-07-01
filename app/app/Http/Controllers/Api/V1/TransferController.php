<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTransferRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\WalletTransferService;
use Illuminate\Http\JsonResponse;

/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class TransferController extends Controller
{
    public function __construct(
        private readonly WalletTransferService $service,
    ) {
    }

    public function __invoke(CreateTransferRequest $request): JsonResponse
    {
        $authenticatedUser = $request->user();
        $payerId = (int) $request->validated('payer');

        if ($authenticatedUser instanceof User && $authenticatedUser->id !== $payerId) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 403);
        }

        $transfer = $this->service->execute(
            $payerId,
            (int) $request->validated('payee'),
            $request->amount(),
            $request->idempotencyKey(),
        );

        $statusCode = $this->resolveStatusCode($transfer);

        return response()->json([
            'data' => [
                'id' => $transfer->id,
                'status' => $transfer->status->value,
                'failure_reason' => $transfer->failure_reason?->value,
            ],
        ], $statusCode);
    }

    private function resolveStatusCode(Transfer $transfer): int
    {
        if (!$transfer->status->isFailed()) {
            return 201;
        }

        return in_array($transfer->failure_reason, [
            FailureReason::SamePayerAndPayee,
            FailureReason::InvalidAmount,
        ], true) ? 201 : 422;
    }
}
