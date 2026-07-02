<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Support\Testing\Fakes\KafkaFake;
use Tests\TestCase;

final class KafkaProduceTransferCommandTest extends TestCase
{
    public function test_publishes_transfer_message_to_kafka(): void
    {
        Kafka::fake();

        $this->artisan('kafka:produce-transfer', [
            'transfer_id' => 1,
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ])
            ->assertSuccessful();

        Kafka::assertPublishedTimes(1);
    }

    public function test_dry_run_outputs_envelope_without_publishing(): void
    {
        Kafka::fake();

        $this->artisan('kafka:produce-transfer', [
            'transfer_id' => 1,
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('wallet.transfer.completed')
            ->expectsOutputToContain('Envelope:');

        Kafka::assertNothingPublished();
    }

    public function test_rejects_invalid_arguments(): void
    {
        Kafka::fake();

        $this->artisan('kafka:produce-transfer', [
            'transfer_id' => 0,
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ])
            ->assertFailed();

        Kafka::assertNothingPublished();
    }
}
