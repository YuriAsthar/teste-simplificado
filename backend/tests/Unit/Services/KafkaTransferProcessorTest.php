<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TransferStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Transfer;
use App\Models\User;
use App\Services\DryRun\DryRunContext;
use App\Services\DryRun\DryRunRecorder;
use App\Services\KafkaTransferProcessor;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class KafkaTransferProcessorTest extends TestCase
{
    public function test_process_dispatches_notification_and_marks_processed(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());
        $connection = $this->mock(stdClass::class);

        $transfer = $this->createCompletedTransfer(123);

        $this->assertFalse($cache->has('kafka:transfer:123'));

        $connection->shouldReceive('onConnection')
            ->once()
            ->with('rabbitmq');

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with($this->callback(static function (SendNotificationJob $job): bool {
                return $job->transferId === 123;
            }))
            ->andReturn($connection);

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        $this->assertTrue($cache->has('kafka:transfer:123'));
        $this->assertNotNull($transfer->fresh());
    }

    public function test_process_skips_duplicate_transfer_id(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());

        $cache->put('kafka:transfer:123', true, 3600);

        $dispatcher->shouldNotReceive('dispatch');

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        $this->assertTrue($cache->has('kafka:transfer:123'));
    }

    public function test_process_throws_invalid_argument_for_missing_transfer_id(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());

        $dispatcher->shouldNotReceive('dispatch');

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing transfer_id');

        $processor->process([
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);
    }

    public function test_process_rethrows_unexpected_exception(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());

        $this->createCompletedTransfer(123);

        $this->assertFalse($cache->has('kafka:transfer:123'));

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('dispatch failed'));

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dispatch failed');

        $processor->process([
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        $this->assertFalse($cache->has('kafka:transfer:123'));
    }

    public function test_process_records_but_does_not_dispatch_in_dry_run(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $this->assertFalse($cache->has('kafka:transfer:123'));

        $dispatcher->shouldNotReceive('dispatch');

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        $entries = $context->flush();

        $this->assertCount(2, $entries);
        $this->assertSame('rabbitmq.dispatch', $entries[0]['action']);
        $this->assertSame('idempotency.skip', $entries[1]['action']);
        $this->assertSame(123, $entries[1]['context']['transfer_id']);
    }

    public function test_process_skips_duplicate_even_in_dry_run(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $cache->put('kafka:transfer:123', true, 3600);

        $dispatcher->shouldNotReceive('dispatch');

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        $this->assertCount(0, $context->flush());
        $this->assertTrue($cache->has('kafka:transfer:123'));
    }

    public function test_process_marks_processed_when_transfer_is_missing(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());

        $dispatcher->shouldNotReceive('dispatch');

        $this->assertFalse($cache->has('kafka:transfer:999'));

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => '999',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        $this->assertTrue($cache->has('kafka:transfer:999'));
    }

    public function test_process_marks_processed_when_transfer_is_not_completed(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());

        $transfer = Transfer::factory()->create(['status' => TransferStatus::Failed]);

        $dispatcher->shouldNotReceive('dispatch');

        $this->assertFalse($cache->has("kafka:transfer:{$transfer->id}"));

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => (string) $transfer->id,
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        $this->assertTrue($cache->has("kafka:transfer:{$transfer->id}"));
    }

    public function test_process_uses_configured_ttl(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = Cache::store();
        $context = new DryRunContext(new DryRunRecorder());
        $connection = $this->mock(stdClass::class);

        $transfer = $this->createCompletedTransfer(123);

        config(['kafka.idempotency_ttl' => 60]);

        $this->assertFalse($cache->has('kafka:transfer:123'));

        $connection->shouldReceive('onConnection')
            ->once()
            ->with('rabbitmq');

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andReturn($connection);

        $processor = new KafkaTransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        $this->assertTrue($cache->has('kafka:transfer:123'));
        $this->assertNotNull($transfer->fresh());
    }

    private function createCompletedTransfer(int $transferId): Transfer
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        return Transfer::factory()->create([
            'id' => $transferId,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount' => 1000,
            'status' => TransferStatus::Completed,
        ]);
    }
}
