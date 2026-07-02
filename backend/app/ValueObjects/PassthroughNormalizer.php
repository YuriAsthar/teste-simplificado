<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\ValueObjects\Contracts\DocumentValueNormalizerInterface;

final readonly class PassthroughNormalizer implements DocumentValueNormalizerInterface
{
    public function normalize(string $value): string
    {
        return trim($value);
    }
}
