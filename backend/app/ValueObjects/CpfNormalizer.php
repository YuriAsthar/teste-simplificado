<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\ValueObjects\Contracts\DocumentValueNormalizerInterface;

final readonly class CpfNormalizer implements DocumentValueNormalizerInterface
{
    public function normalize(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }
}
