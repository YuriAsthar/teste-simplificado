<?php

use App\Enums\IdempotencyKeyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->string('status')->default(IdempotencyKeyStatus::Processing->value);
            $table->string('request_hash')->nullable();
            $table->index('status');
        });

        $rows = DB::table('idempotency_keys')
            ->join('transfers', 'idempotency_keys.transfer_id', '=', 'transfers.id')
            ->whereNotNull('idempotency_keys.transfer_id')
            ->select([
                'idempotency_keys.id as idempotency_key_id',
                'transfers.payer_id',
                'transfers.payee_id',
                'transfers.amount',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('idempotency_keys')
                ->where('id', $row->idempotency_key_id)
                ->update([
                    'status' => IdempotencyKeyStatus::Completed->value,
                    'request_hash' => hash('sha256', implode(':', [$row->payer_id, $row->payee_id, $row->amount])),
                ]);
        }

        DB::table('idempotency_keys')
            ->whereNull('transfer_id')
            ->update([
                'status' => IdempotencyKeyStatus::Completed->value,
                'request_hash' => null,
            ]);
    }
};