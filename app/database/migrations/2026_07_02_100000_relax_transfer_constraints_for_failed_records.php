<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table): void {
            $table->dropForeign(['payer_id']);
            $table->dropForeign(['payee_id']);
        });

        DB::statement('ALTER TABLE transfers DROP CONSTRAINT IF EXISTS chk_transfers_amount_positive');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT chk_transfers_amount_non_negative CHECK (amount >= 0)');
    }

    public function down(): void
    {
        // WARNING: down() re-adds the FK and positive-amount constraints. It will fail if any failed
        // transfer rows reference non-existent users or have amount = 0. In production this migration
        // should be treated as forward-only; roll back only after cleaning up orphan/invalid rows.
        DB::statement('ALTER TABLE transfers DROP CONSTRAINT IF EXISTS chk_transfers_amount_non_negative');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT chk_transfers_amount_positive CHECK (amount > 0)');

        Schema::table('transfers', function (Blueprint $table): void {
            $table->foreign('payer_id')->references('id')->on('users');
            $table->foreign('payee_id')->references('id')->on('users');
        });
    }
};
