<?php

declare(strict_types=1);

namespace App\Enums;

enum OutboxStatus: string
{
    case Pending = 'Pending';
    case Published = 'Published';
    case Failed = 'Failed';
}
