<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('transfers');

        Schema::create('transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payer_id')->constrained('users');
            $table->foreignId('payee_id')->constrained('users');
            $table->bigInteger('amount_cents');
            $table->string('currency', 3)->default('BRA');
            $table->string('idempotency_key')->nullable();
            $table->string('status')->default('pending');
            $table->string('failure_reason')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['payer_id', 'status']);
            $table->index(['payee_id', 'status']);
            $table->index(['status', 'notified_at']);
            $table->index('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');

        Schema::create('transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('payer_wallet_id')->constrained('wallets');
            $table->foreignId('payee_wallet_id')->constrained('wallets');
            $table->bigInteger('amount_cents');
            $table->string('currency', 3)->default('BRA');
            $table->string('idempotency_key');
            $table->string('status')->default('pending');
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['payer_wallet_id', 'status']);
            $table->index(['payee_wallet_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('idempotency_key');
        });
    }
};
