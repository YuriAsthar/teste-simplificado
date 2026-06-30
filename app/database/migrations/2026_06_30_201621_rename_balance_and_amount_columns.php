<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->renameColumn('balance_cents', 'balance');
        });

        Schema::table('transfers', function (Blueprint $table): void {
            $table->renameColumn('amount_cents', 'amount');
        });

        DB::statement('ALTER TABLE wallets ADD CONSTRAINT chk_wallets_balance_non_negative CHECK (balance >= 0)');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT chk_transfers_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transfers DROP CONSTRAINT IF EXISTS chk_transfers_amount_positive');
        DB::statement('ALTER TABLE wallets DROP CONSTRAINT IF EXISTS chk_wallets_balance_non_negative');

        Schema::table('transfers', function (Blueprint $table): void {
            $table->renameColumn('amount', 'amount_cents');
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->renameColumn('balance', 'balance_cents');
        });
    }
};
