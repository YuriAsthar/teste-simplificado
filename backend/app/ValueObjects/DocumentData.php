<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\DocumentType;

final readonly class DocumentData
{
    public string $value;

    public function __construct(
        public string $country,
        public DocumentType $type,
        string $value,
    ) {
        $this->value = DocumentValueNormalizer::normalize($type, $value);
    }
}
