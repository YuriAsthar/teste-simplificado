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
        return hash('sha256', implode(':', [$payerId, $payeeId, $amount]));
    }

    /**
     * @return array{record: IdempotencyKey, created: bool}
     *
     * @throws IdempotencyKeyInProgressException
     * @throws IdempotencyKeyFingerprintMismatchException
     */
    public function acquireOrResolveIdempotencyKey(
        string $idempotencyKey,
        string $fingerprint,
    ): array {
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $result = $this->attemptInsertIdempotencyKey($idempotencyKey, $fingerprint);

            if (!is_null($result)) {
                return $result;
            }

            $existing = IdempotencyKey::query()
                ->where('key', $idempotencyKey)
                ->first();

            if (is_null($existing)) {
                throw new IdempotencyKeyInProgressException();
            }

            if ($existing->fingerprint !== $fingerprint) {
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

    public function finalizeIdempotencyKey(
        string $idempotencyKey,
        string $fingerprint,
        Transfer $transfer,
    ): void {
        IdempotencyKey::updateOrCreate(
            ['key' => $idempotencyKey],
            [
                'status' => IdempotencyKeyStatus::Completed,
                'fingerprint' => $fingerprint,
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
        string $fingerprint,
    ): ?array {
        $results = DB::select(
            'INSERT INTO idempotency_keys (key, status, fingerprint, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?) '
            . 'ON CONFLICT (key) DO NOTHING '
            . 'RETURNING id, key, status, fingerprint, transfer_id',
            [
                $idempotencyKey,
                IdempotencyKeyStatus::Processing->value,
                $fingerprint,
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
