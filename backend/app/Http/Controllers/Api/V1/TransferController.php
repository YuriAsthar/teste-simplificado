<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Exceptions\AuthorizerRejectedException;
use App\Exceptions\TransientAuthorizerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTransferRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\IdempotencyKeyService;
use App\Services\WalletTransferService;
use Illuminate\Http\JsonResponse;

/**
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class TransferController extends Controller
{
    public function __construct(
        private readonly WalletTransferService $service,
        private readonly IdempotencyKeyService $idempotencyService,
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

        $cachedResponse = $this->idempotencyService->tryResolveCachedResponse(
            $idempotencyKey,
            $endpoint,
            $requestHash,
        );

        if (!is_null($cachedResponse)) {
            return response()->json($cachedResponse['body'], $cachedResponse['status']);
        }

        try {
            $transfer = $this->service->execute(
                $payerId,
                (int) $request->validated('payee'),
                $request->amount(),
                $idempotencyKey,
            );
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
            'data' => [
                'id' => $transfer->id,
                'status' => $transfer->status->value,
                'failure_reason' => $transfer->failure_reason?->value,
            ],
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
