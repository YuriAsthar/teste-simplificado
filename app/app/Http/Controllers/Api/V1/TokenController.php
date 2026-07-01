<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\LoginService;
use Illuminate\Http\JsonResponse;

final class TokenController extends Controller
{
    public function __construct(
        private LoginService $loginService,
    ) {
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $token = $this->loginService->issueToken(
            $request->validated('email'),
            $request->validated('password'),
        );

        if (is_null($token)) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
