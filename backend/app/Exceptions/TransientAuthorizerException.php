<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class TransientAuthorizerException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(string $message = 'Authorizer service temporarily unavailable.')
    {
        parent::__construct($message, 503);
    }

    public function getStatusCode(): int
    {
        return 503;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
