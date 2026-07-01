<?php

declare(strict_types=1);

namespace App\ValueObjects\Contracts;

interface DocumentValueNormalizerInterface
{
    public function normalize(string $value): string;
}
