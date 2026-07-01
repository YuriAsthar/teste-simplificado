<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\FailureReason;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AuthorizerRejectedException extends HttpException
{
    public function __construct(string $message = '')
    {
        if ($message === '') {
            $message = FailureReason::AuthorizerRejected->description();
        }

        parent::__construct(422, $message);
    }
}
