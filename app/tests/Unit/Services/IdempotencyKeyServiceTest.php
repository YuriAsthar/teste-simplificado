<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\IdempotencyKeyService;
use PHPUnit\Framework\TestCase;

final class IdempotencyKeyServiceTest extends TestCase
{
    public function test_fingerprint_uses_fixed_payer_payee_amount_order(): void
    {
        $service = new IdempotencyKeyService();

        $this->assertNotSame(
            $service->buildFingerprint(1, 2, 100),
            $service->buildFingerprint(2, 1, 100),
        );
    }

    public function test_fingerprint_is_sha256_of_colon_separated_values(): void
    {
        $service = new IdempotencyKeyService();

        $this->assertSame(
            hash('sha256', '1:2:100'),
            $service->buildFingerprint(1, 2, 100),
        );
    }
}
