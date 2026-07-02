<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\DocumentType;

final class DocumentValueNormalizer
{
    public static function normalize(DocumentType|string $type, string $value): string
    {
        $typeValue = $type instanceof DocumentType ? $type->value : $type;

        return match ($typeValue) {
            DocumentType::BrCpf->value => (new CpfNormalizer())->normalize($value),
            DocumentType::BrCnpj->value => (new CnpjNormalizer())->normalize($value),
            default => (new PassthroughNormalizer())->normalize($value),
        };
    }
}
