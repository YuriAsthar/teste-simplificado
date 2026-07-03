<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

final readonly class TransferMessagePayload
{
    public function __construct(
        public int $transferId,
        public ?int $payerId,
        public ?int $payeeId,
        public ?string $amount,
        public ?string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $transferId = self::extractTransferId($payload);

        if (is_null($transferId)) {
            throw new InvalidArgumentException('missing transfer_id');
        }

        return new self(
            transferId: $transferId,
            payerId: self::extractNullableInt($payload, 'payer_id'),
            payeeId: self::extractNullableInt($payload, 'payee_id'),
            amount: self::extractNullableString($payload, 'amount'),
            status: self::extractNullableString($payload, 'status'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'transfer_id' => $this->transferId,
            'payer_id' => $this->payerId,
            'payee_id' => $this->payeeId,
            'amount' => $this->amount,
            'status' => $this->status,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractTransferId(array $payload): ?int
    {
        $value = $payload['transfer_id'] ?? null;

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractNullableInt(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        if (is_null($value)) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractNullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (is_null($value)) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
