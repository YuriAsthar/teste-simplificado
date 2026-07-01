<?php

declare(strict_types=1);

namespace Tests\Unit\Validators;

use App\Validators\CpfCnpjValidator;
use PHPUnit\Framework\TestCase;

final class CpfCnpjValidatorTest extends TestCase
{
    public function test_it_accepts_valid_unformatted_cpf(): void
    {
        $this->assertTrue(CpfCnpjValidator::isValidCpf('52998224725'));
    }

    public function test_it_accepts_valid_formatted_cpf(): void
    {
        $this->assertTrue(CpfCnpjValidator::isValidCpf('529.982.247-25'));
    }

    public function test_it_rejects_invalid_cpf_check_digits(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCpf('12345678901'));
    }

    public function test_it_rejects_cpf_with_repeated_digits(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCpf('11111111111'));
    }

    public function test_it_rejects_cpf_with_wrong_length(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCpf('5299822472'));
        $this->assertFalse(CpfCnpjValidator::isValidCpf('529982247251'));
    }

    public function test_it_rejects_cpf_with_letters(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCpf('5299822472a'));
    }

    public function test_it_accepts_valid_unformatted_cnpj(): void
    {
        $this->assertTrue(CpfCnpjValidator::isValidCnpj('11222333000181'));
    }

    public function test_it_accepts_valid_formatted_cnpj(): void
    {
        $this->assertTrue(CpfCnpjValidator::isValidCnpj('11.222.333/0001-81'));
    }

    public function test_it_rejects_invalid_cnpj_check_digits(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCnpj('11222333000195'));
    }

    public function test_it_rejects_cnpj_with_repeated_digits(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCnpj('22222222222222'));
    }

    public function test_it_rejects_cnpj_with_wrong_length(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCnpj('1122233300018'));
        $this->assertFalse(CpfCnpjValidator::isValidCnpj('112223330001811'));
    }

    public function test_it_rejects_cnpj_with_invalid_alphanumeric_check_digits(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCnpj('12ABC345000199'));
    }

    public function test_it_accepts_valid_alphanumeric_cnpj(): void
    {
        $this->assertTrue(CpfCnpjValidator::isValidCnpj('12ABC345000188'));
    }

    public function test_it_accepts_valid_formatted_alphanumeric_cnpj(): void
    {
        $this->assertTrue(CpfCnpjValidator::isValidCnpj('12.ABC.345/0001-88'));
    }

    public function test_it_rejects_cnpj_with_repeated_letters(): void
    {
        $this->assertFalse(CpfCnpjValidator::isValidCnpj('AAAAAAAAAAAAAA'));
    }
}
