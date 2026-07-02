<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Validates Brazilian CPF and CNPJ numbers.
 *
 * Both formatted (with dots, hyphens and slashes) and unformatted values
 * are accepted. The validation follows the official check-digit algorithms
 * and rejects sequences of repeated digits.
 */
final class CpfCnpjValidator
{
    private const CPF_LENGTH = 11;

    private const CNPJ_LENGTH = 14;

    /**
     * @var list<int>
     */
    private const CPF_WEIGHTS_FIRST = [10, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * @var list<int>
     */
    private const CPF_WEIGHTS_SECOND = [11, 10, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * @var list<int>
     */
    private const CNPJ_WEIGHTS_FIRST = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * @var list<int>
     */
    private const CNPJ_WEIGHTS_SECOND = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public static function isValidCpf(string $value): bool
    {
        $digits = self::digitsOnly($value);

        if (strlen($digits) !== self::CPF_LENGTH) {
            return false;
        }

        if (self::allSameCharacter($digits)) {
            return false;
        }

        $firstDigit = self::calculateCheckDigit($digits, self::CPF_WEIGHTS_FIRST);
        $secondDigit = self::calculateCheckDigit($digits, self::CPF_WEIGHTS_SECOND);

        return $digits[9] === $firstDigit && $digits[10] === $secondDigit;
    }

    public static function isValidCnpj(string $value): bool
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value));

        if (strlen($normalized) !== self::CNPJ_LENGTH) {
            return false;
        }

        if (!self::isAlphanumericCnpj($normalized)) {
            return false;
        }

        if (self::allSameCharacter($normalized)) {
            return false;
        }

        $firstDigit = self::calculateAlphanumericCheckDigit($normalized, self::CNPJ_WEIGHTS_FIRST);
        $secondDigit = self::calculateAlphanumericCheckDigit($normalized, self::CNPJ_WEIGHTS_SECOND);

        return $normalized[12] === $firstDigit && $normalized[13] === $secondDigit;
    }

    private static function digitsOnly(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    private static function isAlphanumericCnpj(string $value): bool
    {
        return preg_match('/^[A-Z0-9]{' . self::CNPJ_LENGTH . '}$/', $value) === 1;
    }

    private static function allSameCharacter(string $digits): bool
    {
        $first = $digits[0] ?? '';

        foreach (str_split($digits) as $digit) {
            if ($digit !== $first) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $weights
     */
    private static function calculateCheckDigit(string $digits, array $weights): string
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $digits[$index] * $weight;
        }

        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }

    /**
     * @param list<int> $weights
     */
    private static function calculateAlphanumericCheckDigit(string $digits, array $weights): string
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += self::alphanumericValue($digits[$index]) * $weight;
        }

        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? 0 : 11 - $remainder);
    }

    private static function alphanumericValue(string $character): int
    {
        return ord($character) - 48;
    }
}
