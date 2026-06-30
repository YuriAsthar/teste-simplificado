<?php

declare(strict_types=1);

namespace App\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Authorized, self::Failed, self::Cancelled], true),
            self::Authorized => in_array($target, [self::Completed, self::Failed], true),
            self::Completed => $target === self::Refunded,
            default => false,
        };
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
