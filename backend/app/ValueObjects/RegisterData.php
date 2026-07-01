<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\UserType;

final readonly class RegisterData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public UserType $type,
        public ?DocumentData $document = null,
    ) {
    }
}
