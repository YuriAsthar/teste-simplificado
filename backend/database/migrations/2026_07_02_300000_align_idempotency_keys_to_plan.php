<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->string('endpoint')->nullable();
            $table->string('request_hash')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
        });

        DB::table('idempotency_keys')
            ->whereNotNull('fingerprint')
            ->update(['request_hash' => DB::raw('fingerprint')]);
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropColumn(['endpoint', 'request_hash', 'response_status', 'response_body']);
        });
    }
};
