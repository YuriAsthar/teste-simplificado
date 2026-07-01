<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MoneyParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyParserTest extends TestCase
{
    public function test_parses_whole_amounts_to_cents(): void
    {
        $this->assertSame(1000, MoneyParser::parseToCents('10'));
    }

    public function test_parses_one_digit_fraction(): void
    {
        $this->assertSame(1050, MoneyParser::parseToCents('10.5'));
    }

    public function test_parses_two_digit_fraction(): void
    {
        $this->assertSame(1050, MoneyParser::parseToCents('10.50'));
    }

    public function test_parses_small_fractions(): void
    {
        $this->assertSame(1, MoneyParser::parseToCents('0.01'));
    }

    public function test_parses_zero(): void
    {
        $this->assertSame(0, MoneyParser::parseToCents('0'));
        $this->assertSame(0, MoneyParser::parseToCents('0.00'));
    }

    public function test_parses_large_values(): void
    {
        $this->assertSame(12345678901234567, MoneyParser::parseToCents('123456789012345.67'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidValueProvider(): array
    {
        return [
            'comma separator' => ['10,50'],
            'three fraction digits' => ['10.500'],
            'fraction overflow' => ['10.999'],
            'trailing dot' => ['10.'],
            'leading dot' => ['.50'],
            'negative' => ['-10.50'],
            'scientific notation' => ['1e2'],
            'hex' => ['0x10'],
            'empty' => [''],
            'whitespace' => ['   '],
            'non-numeric' => ['abc'],
            'spaces inside' => ['10 .50'],
        ];
    }

    #[DataProvider('invalidValueProvider')]
    public function test_rejects_invalid_values(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        MoneyParser::parseToCents($value);
    }

    public function test_uses_only_integer_arithmetic(): void
    {
        $reflection = new \ReflectionClass(MoneyParser::class);
        $source = (string) file_get_contents((string) $reflection->getFileName());

        $this->assertStringNotContainsString('(float)', $source);
        $this->assertStringNotContainsString('(double)', $source);
        $this->assertStringNotContainsString('bcmul', $source);
        $this->assertStringNotContainsString('round(', $source);
        $this->assertStringNotContainsString('intval(', $source);
    }
}
