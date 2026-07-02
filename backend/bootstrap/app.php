<?php

use App\Exceptions\AuthorizerRejectedException;
use App\Exceptions\IdempotencyKeyFingerprintMismatchException;
use App\Exceptions\IdempotencyKeyInProgressException;
use App\Exceptions\TransientAuthorizerException;
use App\Support\DatabaseErrorResponse;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*') || $request->expectsJson()) {
                abort(response()->json(['message' => 'Unauthenticated.'], 401));
            }

            return Route::has('login') ? route('login') : null;
        });
    })
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        });

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

        $exceptions->renderable(function (AuthorizerRejectedException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'authorizer_rejected',
            ], $exception->getStatusCode());
        });

        $exceptions->renderable(function (UniqueConstraintViolationException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'The provided data conflicts with an existing record.',
                'code' => 'duplicate_record',
            ], 422);
        });

        $exceptions->renderable(function (QueryException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            $code = $exception->getCode();
            if ($exception->getPrevious() instanceof \PDOException) {
                $code = $exception->getPrevious()->getCode();
            }

            return DatabaseErrorResponse::make($code, $request, $exception);
        });

        $exceptions->renderable(function (\PDOException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return DatabaseErrorResponse::make($exception->getCode(), $request, $exception);
        });
    })->create();
