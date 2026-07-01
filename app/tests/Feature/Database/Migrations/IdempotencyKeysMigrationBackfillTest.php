<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Migrations;

use App\Enums\IdempotencyKeyStatus;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use App\Services\IdempotencyKeyService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class IdempotencyKeysMigrationBackfillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh');
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh');

        parent::tearDown();
    }

    public function test_migration_backfills_existing_rows_with_completed_status_and_fingerprint(): void
    {
        $migration = require database_path('migrations/2026_07_02_000000_add_status_and_fingerprint_to_idempotency_keys.php');
        $migration->down();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount' => 1500,
            'status' => TransferStatus::Completed,
        ]);

        $legacyKeyId = DB::table('idempotency_keys')->insertGetId([
            'key' => 'legacy-key',
            'transfer_id' => $transfer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orphanKeyId = DB::table('idempotency_keys')->insertGetId([
            'key' => 'orphan-key',
            'transfer_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $legacyKey = DB::table('idempotency_keys')->where('id', $legacyKeyId)->first();
        $orphanKey = DB::table('idempotency_keys')->where('id', $orphanKeyId)->first();
        $expectedFingerprint = (new IdempotencyKeyService())->buildFingerprint($payer->id, $payee->id, 1500);

        $this->assertNotNull($legacyKey);
        $this->assertSame(IdempotencyKeyStatus::Completed->value, $legacyKey->status);
        $this->assertSame($expectedFingerprint, $legacyKey->fingerprint);

        $this->assertNotNull($orphanKey);
        $this->assertSame(IdempotencyKeyStatus::Completed->value, $orphanKey->status);
        $this->assertNull($orphanKey->fingerprint);
    }
}
