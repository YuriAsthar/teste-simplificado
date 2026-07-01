<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class DatabaseErrorResponse
{
    private const array CONSTRAINT_VIOLATION_CODES = [
        '23505',
        '23503',
        '23514',
        '23502',
    ];

    public static function make(string|int $sqlState, Request $request, Throwable $exception): JsonResponse
    {
        $normalizedSqlState = str_pad((string) $sqlState, 5, '0', STR_PAD_LEFT);

        if (str_starts_with($normalizedSqlState, '08')) {
            Log::error('Database connection failed', [
                'path' => $request->getPathInfo(),
                'sql_state' => $normalizedSqlState,
            ]);

            return response()->json([
                'message' => 'Service temporarily unavailable. Please try again later.',
                'code' => 'database_connection_error',
            ], 503);
        }

        if (in_array($normalizedSqlState, self::CONSTRAINT_VIOLATION_CODES, true)) {
            return response()->json([
                'message' => 'The provided data is invalid or conflicts with an existing record.',
                'code' => 'database_constraint_violation',
            ], 422);
        }

        Log::error('Database error', [
            'path' => $request->getPathInfo(),
            'sql_state' => $normalizedSqlState,
            'exception' => get_class($exception),
        ]);

        return response()->json([
            'message' => 'An internal error occurred. Please try again later.',
            'code' => 'internal_error',
        ], 500);
    }
}
