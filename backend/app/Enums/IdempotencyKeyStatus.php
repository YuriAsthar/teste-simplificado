<?php

declare(strict_types=1);

namespace App\Enums;

enum IdempotencyKeyStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
}
