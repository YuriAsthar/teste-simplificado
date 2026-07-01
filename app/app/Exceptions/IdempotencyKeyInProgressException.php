<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class IdempotencyKeyInProgressException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(string $message = 'Transfer is already in progress for this idempotency key.')
    {
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
