<?php

use App\Exceptions\IdempotencyKeyFingerprintMismatchException;
use App\Exceptions\IdempotencyKeyInProgressException;
use App\Exceptions\TransientAuthorizerException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // API-only application. Web routes exist only for the root health check.
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->renderable(function (IdempotencyKeyInProgressException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'transfer_in_progress',
            ], $exception->getStatusCode());
        });

        $exceptions->renderable(function (IdempotencyKeyFingerprintMismatchException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'idempotency_key_reuse_with_different_payload',
            ], $exception->getStatusCode());
        });

        $exceptions->renderable(function (TransientAuthorizerException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'authorizer_unavailable',
            ], $exception->getStatusCode());
        });
    })->create();
