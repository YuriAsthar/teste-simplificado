<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\LogoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class LogoutController
{
    public function __construct(
        private readonly LogoutService $logoutService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->logoutService->revokeCurrentToken($request);

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }
}
