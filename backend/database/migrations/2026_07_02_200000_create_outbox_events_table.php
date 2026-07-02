<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('Pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            $table->unique(['aggregate_type', 'aggregate_id', 'event_type']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'attempts', 'last_error_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
