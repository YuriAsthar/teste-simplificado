<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Exceptions\AuthorizerRejectedException;
use App\Exceptions\TransientAuthorizerException;
use App\Http\Requests\CreateTransferRequest;
use App\Http\Resources\TransferResponseResource;
use App\Models\Transfer;
use App\Models\User;
use App\Services\IdempotencyKeyService;
use App\Services\WalletTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final readonly class TransferController
{
    public function __construct(
        private WalletTransferService $service,
        private IdempotencyKeyService $idempotencyService,
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

        $idempotencyKey = $request->idempotencyKey();
        $requestHash = $this->idempotencyService->buildRequestHashFromValidated($request->validated());
        $endpoint = $request->route()?->uri() ?? '/api/v1/transfer';

        Log::info('Checking idempotency key.', [
            'payer_id' => $payerId,
            'idempotency_key' => $idempotencyKey,
        ]);

        $cachedResponse = $this->idempotencyService->tryResolveCachedResponse(
            $idempotencyKey,
            $endpoint,
            $requestHash,
        );

        if (!is_null($cachedResponse)) {
            Log::info('Returning cached idempotency response.', [
                'payer_id' => $payerId,
                'idempotency_key' => $idempotencyKey,
            ]);

            return response()->json($cachedResponse['body'], $cachedResponse['status']);
        }

        try {
            $transfer = $this->service->execute(
                $payerId,
                (int) $request->validated('payee'),
                $request->amount(),
                $idempotencyKey,
            );

            Log::info('Transfer created.', [
                'transfer_id' => $transfer->id,
                'payer_id' => $payerId,
                'payee_id' => (int) $request->validated('payee'),
                'status' => $transfer->status->value,
            ]);
        } catch (AuthorizerRejectedException) {
            return response()->json([
                'code' => 'authorizer_rejected',
                'message' => FailureReason::AuthorizerRejected->description(),
            ], 422);
        } catch (TransientAuthorizerException) {
            return response()->json([
                'code' => 'authorizer_unavailable',
                'message' => 'Authorizer temporarily unavailable. Please retry.',
            ], 503);
        }

        $statusCode = $this->resolveStatusCode($transfer);
        $responseBody = [
            'data' => new TransferResponseResource($transfer),
        ];

        if (!$transfer->status->isFailed()) {
            $this->idempotencyService->finalizeIdempotencyKey(
                $idempotencyKey,
                $requestHash,
                $transfer,
                $endpoint,
                $statusCode,
                $responseBody,
            );
        }

        return response()->json($responseBody, $statusCode);
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
