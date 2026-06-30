<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payer_id' => ['required', 'integer', 'exists:users,id'],
            'payee_id' => ['required', 'integer', 'exists:users,id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->transferService->authorizeAndExecuteTransfer(
            $validated['payer_id'],
            $validated['payee_id'],
            $validated['amount_cents'],
        );

        return response()->json([
            'data' => $result,
        ], 201);
    }
}
