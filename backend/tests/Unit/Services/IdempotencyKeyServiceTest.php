<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\IdempotencyKeyStatus;
use App\Enums\TransferStatus;
use App\Models\IdempotencyKey;
use App\Models\Transfer;
use App\Models\User;
use App\Services\IdempotencyKeyService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class IdempotencyKeyServiceTest extends TestCase
{
    public function test_request_hash_uses_fixed_payer_payee_amount_order(): void
    {
        $service = new IdempotencyKeyService();

        $this->assertNotSame(
            $service->buildRequestHash(1, 2, 100),
            $service->buildRequestHash(2, 1, 100),
        );
    }

    public function test_request_hash_is_sha256_of_colon_separated_values(): void
    {
        $service = new IdempotencyKeyService();

        $this->assertSame(
            hash('sha256', '1:2:100'),
            $service->buildRequestHash(1, 2, 100),
        );
    }

    public function test_fingerprint_alias_matches_request_hash(): void
    {
        $service = new IdempotencyKeyService();

        $this->assertSame(
            $service->buildRequestHash(1, 2, 100),
            $service->buildFingerprint(1, 2, 100),
        );
    }

    public function test_try_resolve_cached_response_returns_null_when_no_match(): void
    {
        $service = new IdempotencyKeyService();

        $result = $service->tryResolveCachedResponse('missing-key', '/api/v1/transfer', 'hash');

        $this->assertNull($result);
    }

    public function test_try_resolve_cached_response_returns_cached_body_and_status(): void
    {
        $service = new IdempotencyKeyService();

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $transfer = Transfer::factory()->create([
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'status' => TransferStatus::Completed,
        ]);

        $requestHash = $service->buildRequestHash($payer->id, $payee->id, 1000);

        IdempotencyKey::factory()->create([
            'key' => 'cached-key',
            'status' => IdempotencyKeyStatus::Completed,
            'request_hash' => $requestHash,
            'endpoint' => '/api/v1/transfer',
            'response_status' => 201,
            'response_body' => ['data' => ['id' => $transfer->id]],
            'transfer_id' => $transfer->id,
        ]);

        $cached = $service->tryResolveCachedResponse('cached-key', '/api/v1/transfer', $requestHash);

        $this->assertNotNull($cached);
        $this->assertSame(201, $cached['status']);
        $this->assertSame(['data' => ['id' => $transfer->id]], $cached['body']);
    }

    public function test_try_resolve_cached_response_ignores_completed_key_with_different_hash(): void
    {
        $service = new IdempotencyKeyService();

        IdempotencyKey::factory()->create([
            'key' => 'mismatched-key',
            'status' => IdempotencyKeyStatus::Completed,
            'request_hash' => 'abc',
            'endpoint' => '/api/v1/transfer',
            'response_status' => 201,
            'response_body' => ['data' => []],
        ]);

        $cached = $service->tryResolveCachedResponse('mismatched-key', '/api/v1/transfer', 'def');

        $this->assertNull($cached);
    }

    public function test_finalize_stores_endpoint_and_response_fields(): void
    {
        $service = new IdempotencyKeyService();

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $transfer = Transfer::factory()->create([
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'status' => TransferStatus::Completed,
        ]);

        $requestHash = $service->buildRequestHash($payer->id, $payee->id, 1000);

        $service->finalizeIdempotencyKey(
            'finalize-key',
            $requestHash,
            $transfer,
            '/api/v1/transfer',
            201,
            ['data' => ['id' => $transfer->id]],
        );

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'finalize-key',
            'status' => IdempotencyKeyStatus::Completed->value,
            'request_hash' => $requestHash,
            'endpoint' => '/api/v1/transfer',
            'response_status' => 201,
        ]);
    }
}
