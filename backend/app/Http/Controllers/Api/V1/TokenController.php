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
        $result = $this->loginService->issueToken(
            $request->validated('email'),
            $request->validated('password'),
        );

        if (is_null($result)) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $user = $result['user'];
        $accessToken = $result['access_token'];

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'token' => $accessToken->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
