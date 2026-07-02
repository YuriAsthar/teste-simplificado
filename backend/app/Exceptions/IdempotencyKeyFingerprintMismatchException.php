<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * @SuppressWarnings("PHPMD.LongClassName")
 */
final class IdempotencyKeyFingerprintMismatchException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        string $message = 'Idempotency key was previously used with a different payload.',
    ) {
        parent::__construct($message, 409);
    }

    public function getStatusCode(): int
    {
        return 409;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
