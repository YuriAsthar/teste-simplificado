<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Jobs\SendNotificationJob;
use App\Services\DryRun\DryRunContext;
use App\Services\DryRun\DryRunRecorder;
use App\Services\TransferProcessor;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class TransferProcessorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_process_dispatches_notification_and_marks_processed(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = $this->mock(Repository::class);
        $connection = $this->mock(stdClass::class);
        $context = new DryRunContext(new DryRunRecorder());

        $cache->shouldReceive('has')
            ->once()
            ->with('kafka:transfer:txn_123')
            ->andReturn(false);

        $cache->shouldReceive('put')
            ->once()
            ->with('kafka:transfer:txn_123', true, 3600);

        $connection->shouldReceive('onConnection')
            ->once()
            ->with('rabbitmq');

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with($this->callback(static function (SendNotificationJob $job): bool {
                return $job->transferId === 0;
            }))
            ->andReturn($connection);

        $processor = new TransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 1000,
        ]);
    }

    public function test_process_skips_duplicate_transfer_id(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = $this->mock(Repository::class);
        $context = new DryRunContext(new DryRunRecorder());

        $dispatcher->shouldNotReceive('dispatch');

        $cache->shouldReceive('has')
            ->once()
            ->with('kafka:transfer:txn_123')
            ->andReturn(true);

        $cache->shouldNotReceive('put');

        $processor = new TransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 1000,
        ]);
    }

    public function test_process_throws_invalid_argument_for_missing_transfer_id(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = $this->mock(Repository::class);
        $context = new DryRunContext(new DryRunRecorder());

        $dispatcher->shouldNotReceive('dispatch');
        $cache->shouldNotReceive('has');

        $processor = new TransferProcessor($dispatcher, $cache, $context);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing transfer_id');

        $processor->process([
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 1000,
        ]);
    }

    public function test_process_rethrows_unexpected_exception(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = $this->mock(Repository::class);
        $context = new DryRunContext(new DryRunRecorder());

        $cache->shouldReceive('has')
            ->once()
            ->with('kafka:transfer:txn_123')
            ->andReturn(false);

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('dispatch failed'));

        $cache->shouldNotReceive('put');

        $processor = new TransferProcessor($dispatcher, $cache, $context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dispatch failed');

        $processor->process([
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 1000,
        ]);
    }

    public function test_process_records_but_does_not_dispatch_in_dry_run(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = $this->mock(Repository::class);
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $cache->shouldReceive('has')
            ->once()
            ->with('kafka:transfer:txn_123')
            ->andReturn(false);

        $dispatcher->shouldNotReceive('dispatch');
        $cache->shouldNotReceive('put');

        $processor = new TransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 1000,
        ]);

        $entries = $context->flush();

        $this->assertCount(2, $entries);
        $this->assertSame('rabbitmq.dispatch', $entries[0]['action']);
        $this->assertSame('idempotency.skip', $entries[1]['action']);
        $this->assertSame('txn_123', $entries[1]['context']['transfer_id']);
    }

    public function test_process_skips_duplicate_even_in_dry_run(): void
    {
        $dispatcher = $this->mock(Dispatcher::class);
        $cache = $this->mock(Repository::class);
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $dispatcher->shouldNotReceive('dispatch');

        $cache->shouldReceive('has')
            ->once()
            ->with('kafka:transfer:txn_123')
            ->andReturn(true);

        $cache->shouldNotReceive('put');

        $processor = new TransferProcessor($dispatcher, $cache, $context);
        $processor->process([
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 1000,
        ]);

        $this->assertCount(0, $context->flush());
    }
}
