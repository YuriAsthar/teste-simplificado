<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Migrations;

use App\Enums\IdempotencyKeyStatus;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use App\Services\IdempotencyKeyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_migration_backfills_existing_rows_with_completed_status_and_request_hash(): void
    {
        DB::table('migrations')
            ->where('migration', '2026_07_02_000000_add_status_and_request_hash_to_idempotency_keys')
            ->delete();

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropColumn(['status', 'request_hash']);
        });

        $payer = User::factory()->create();
        $payee = User::factory()->create();

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

        Artisan::call('migrate');

        $legacyKey = DB::table('idempotency_keys')->where('id', $legacyKeyId)->first();
        $orphanKey = DB::table('idempotency_keys')->where('id', $orphanKeyId)->first();
        $expectedHash = (new IdempotencyKeyService())->buildRequestHash($payer->id, $payee->id, 1500);

        $this->assertNotNull($legacyKey);
        $this->assertSame(IdempotencyKeyStatus::Completed->value, $legacyKey->status);
        $this->assertSame($expectedHash, $legacyKey->request_hash);

        $this->assertNotNull($orphanKey);
        $this->assertSame(IdempotencyKeyStatus::Completed->value, $orphanKey->status);
        $this->assertNull($orphanKey->request_hash);
    }

    public function test_align_migration_adds_response_columns(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('idempotency_keys');

        $this->assertContains('endpoint', $columns);
        $this->assertContains('request_hash', $columns);
        $this->assertContains('response_status', $columns);
        $this->assertContains('response_body', $columns);
    }
}
