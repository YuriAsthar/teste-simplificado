<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\LoginResponseResource;
use App\Services\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final readonly class LoginController
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
            Log::warning('Login failed: invalid credentials.', [
                'email' => $request->validated('email'),
            ]);

            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        Log::info('Login succeeded.', [
            'user_id' => $result['user']->id,
        ]);

        return response()->json([
            'data' => new LoginResponseResource(
                $result['user'],
                $result['access_token'],
            ),
        ]);
    }
}
