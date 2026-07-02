<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\ValueObjects\Contracts\DocumentValueNormalizerInterface;

final readonly class CnpjNormalizer implements DocumentValueNormalizerInterface
{
    public function normalize(string $value): string
    {
        $trimmed = trim($value);

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $trimmed));
    }
}
