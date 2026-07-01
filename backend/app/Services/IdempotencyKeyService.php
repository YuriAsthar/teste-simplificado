<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IdempotencyKeyStatus;
use App\Exceptions\IdempotencyKeyFingerprintMismatchException;
use App\Exceptions\IdempotencyKeyInProgressException;
use App\Models\IdempotencyKey;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

final readonly class IdempotencyKeyService
{
    public function buildFingerprint(int $payerId, int $payeeId, int $amount): string
    {
        return $this->buildRequestHash($payerId, $payeeId, $amount);
    }

    public function buildRequestHash(int $payerId, int $payeeId, int $amount): string
    {
        return hash('sha256', implode(':', [$payerId, $payeeId, $amount]));
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function buildRequestHashFromValidated(array $validated): string
    {
        return $this->buildRequestHash(
            (int) ($validated['payer'] ?? 0),
            (int) ($validated['payee'] ?? 0),
            (int) ($validated['amount'] ?? 0),
        );
    }

    /**
     * @return array{record: IdempotencyKey, created: bool}
     *
     * @throws IdempotencyKeyInProgressException
     * @throws IdempotencyKeyFingerprintMismatchException
     */
    public function acquireOrResolveIdempotencyKey(
        string $idempotencyKey,
        string $requestHash,
    ): array {
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $result = $this->attemptInsertIdempotencyKey($idempotencyKey, $requestHash);

            if (!is_null($result)) {
                return $result;
            }

            $existing = IdempotencyKey::query()
                ->where('key', $idempotencyKey)
                ->first();

            if (is_null($existing)) {
                throw new IdempotencyKeyInProgressException();
            }

            $existingHash = $existing->request_hash ?? $existing->fingerprint;

            if ($existingHash !== $requestHash) {
                throw new IdempotencyKeyFingerprintMismatchException();
            }

            if ($this->isStaleProcessingIdempotencyKey($existing)) {
                $existing->delete();

                continue;
            }

            return ['record' => $existing, 'created' => false];
        }

        throw new IdempotencyKeyInProgressException();
    }

    /**
     * @return array{status: int, body: array<string, mixed>}|null
     */
    public function tryResolveCachedResponse(
        string $idempotencyKey,
        string $endpoint,
        string $requestHash,
    ): ?array {
        $existing = IdempotencyKey::query()
            ->where('key', $idempotencyKey)
            ->where('status', IdempotencyKeyStatus::Completed)
            ->where('endpoint', $endpoint)
            ->where(static function ($query) use ($requestHash): void {
                $query->where('request_hash', $requestHash)
                    ->orWhere('fingerprint', $requestHash);
            })
            ->whereNotNull('response_status')
            ->first();

        if (is_null($existing)) {
            return null;
        }

        /** @var array<string, mixed> $body */
        $body = $existing->response_body ?? [];

        return [
            'status' => (int) $existing->response_status,
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $responseBody
     */
    public function finalizeIdempotencyKey(
        string $idempotencyKey,
        string $requestHash,
        Transfer $transfer,
        string $endpoint,
        int $responseStatus,
        array $responseBody,
    ): void {
        IdempotencyKey::updateOrCreate(
            ['key' => $idempotencyKey],
            [
                'status' => IdempotencyKeyStatus::Completed,
                'request_hash' => $requestHash,
                'endpoint' => $endpoint,
                'response_status' => $responseStatus,
                'response_body' => $responseBody,
                'transfer_id' => $transfer->id,
            ],
        );
    }

    public function finalizeIdempotencyKeyWithoutResponse(
        string $idempotencyKey,
        string $requestHash,
        Transfer $transfer,
    ): void {
        IdempotencyKey::updateOrCreate(
            ['key' => $idempotencyKey],
            [
                'status' => IdempotencyKeyStatus::Completed,
                'request_hash' => $requestHash,
                'transfer_id' => $transfer->id,
            ],
        );
    }

    public function deleteProcessingIdempotencyKey(string $idempotencyKey): void
    {
        IdempotencyKey::query()
            ->where('key', $idempotencyKey)
            ->where('status', IdempotencyKeyStatus::Processing)
            ->delete();
    }

    /**
     * @return array{record: IdempotencyKey, created: bool}|null
     */
    private function attemptInsertIdempotencyKey(
        string $idempotencyKey,
        string $requestHash,
    ): ?array {
        $results = DB::select(
            'INSERT INTO idempotency_keys (key, status, request_hash, fingerprint, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?) '
            . 'ON CONFLICT (key) DO NOTHING '
            . 'RETURNING id, key, status, request_hash, fingerprint, transfer_id',
            [
                $idempotencyKey,
                IdempotencyKeyStatus::Processing->value,
                $requestHash,
                $requestHash,
                now()->toDateTimeString(),
                now()->toDateTimeString(),
            ]
        );

        if (count($results) === 0) {
            return null;
        }

        $record = new IdempotencyKey((array) $results[0]);
        $record->exists = true;
        $record->syncOriginal();

        return ['record' => $record, 'created' => true];
    }

    private function isStaleProcessingIdempotencyKey(IdempotencyKey $idempotencyKey): bool
    {
        if ($idempotencyKey->status !== IdempotencyKeyStatus::Processing) {
            return false;
        }

        $ttlSeconds = (int) config('transfer.idempotency_processing_ttl_seconds', 300);

        return $idempotencyKey->updated_at->diffInSeconds(now()) > $ttlSeconds;
    }
}
