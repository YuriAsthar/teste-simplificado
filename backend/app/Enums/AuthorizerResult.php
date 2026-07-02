<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthorizerResult: string
{
    case Authorized = 'authorized';
    case Rejected = 'rejected';
    case Transient = 'transient';
}
