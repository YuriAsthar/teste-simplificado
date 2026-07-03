<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RegisterRequest;
use App\Http\Resources\RegisterResponseResource;
use App\Services\RegisterService;
use Illuminate\Http\JsonResponse;

final readonly class RegisterController
{
    public function __construct(
        private RegisterService $registerService,
    ) {
    }

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $result = $this->registerService->register($request->registerData());

        return response()->json([
            'data' => new RegisterResponseResource(
                $result['user'],
                $result['access_token'],
            ),
        ], 201);
    }
}
