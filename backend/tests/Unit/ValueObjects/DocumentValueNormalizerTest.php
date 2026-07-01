<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\Enums\DocumentType;
use App\ValueObjects\DocumentValueNormalizer;
use PHPUnit\Framework\TestCase;

final class DocumentValueNormalizerTest extends TestCase
{
    public function test_it_normalizes_formatted_cpf_to_digits(): void
    {
        $this->assertSame(
            '52998224725',
            DocumentValueNormalizer::normalize(DocumentType::BrCpf, '529.982.247-25'),
        );
    }

    public function test_it_preserves_already_normalized_cpf(): void
    {
        $this->assertSame(
            '52998224725',
            DocumentValueNormalizer::normalize(DocumentType::BrCpf, '52998224725'),
        );
    }

    public function test_it_preserves_cpf_leading_zeros(): void
    {
        $this->assertSame(
            '01234567890',
            DocumentValueNormalizer::normalize(DocumentType::BrCpf, '012.345.678-90'),
        );
    }

    public function test_it_normalizes_formatted_legacy_cnpj_to_digits(): void
    {
        $this->assertSame(
            '11222333000181',
            DocumentValueNormalizer::normalize(DocumentType::BrCnpj, '11.222.333/0001-81'),
        );
    }

    public function test_it_normalizes_alphanumeric_cnpj_to_uppercase(): void
    {
        $this->assertSame(
            '12ABC3450001DE',
            DocumentValueNormalizer::normalize(DocumentType::BrCnpj, '12.ABC.345/0001-DE'),
        );
    }

    public function test_it_uppercases_lowercase_cnpj_letters(): void
    {
        $this->assertSame(
            '12ABC3450001DE',
            DocumentValueNormalizer::normalize(DocumentType::BrCnpj, '12.abc.345/0001-de'),
        );
    }

    public function test_it_preserves_cnpj_leading_zeros(): void
    {
        $this->assertSame(
            '01AB23450001DE',
            DocumentValueNormalizer::normalize(DocumentType::BrCnpj, '01.AB2.345/0001-DE'),
        );
    }

    public function test_it_removes_cnpj_separators_and_spaces(): void
    {
        $this->assertSame(
            '11222333000181',
            DocumentValueNormalizer::normalize(DocumentType::BrCnpj, ' 11.222.333/0001-81 '),
        );
    }

    public function test_it_removes_cnpj_underscores_and_accented_characters(): void
    {
        $this->assertSame(
            '12ABC34501DE',
            DocumentValueNormalizer::normalize(DocumentType::BrCnpj, '12_Abc.345_á_01-de'),
        );
    }

    public function test_it_trims_other_document_type_values(): void
    {
        $this->assertSame(
            'us_ein_value',
            DocumentValueNormalizer::normalize(DocumentType::UsEin, '  us_ein_value  '),
        );
    }

    public function test_it_falls_back_to_passthrough_for_unknown_type_string(): void
    {
        $this->assertSame(
            'kept-as-is',
            DocumentValueNormalizer::normalize('unknown_type', ' kept-as-is '),
        );
    }
}
