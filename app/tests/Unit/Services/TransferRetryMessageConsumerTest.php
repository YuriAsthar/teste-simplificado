<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DryRun\DryRunContext;
use App\Services\DryRun\DryRunRecorder;
use App\Services\KafkaTransferProcessor;
use App\Services\TransferRetryMessageConsumer;
use App\Services\TransferRetryPolicy;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class TransferRetryMessageConsumerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reprocesses_message_when_due(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);
        $context = new DryRunContext(new DryRunRecorder());

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $processor->shouldReceive('process')
            ->once()
            ->with($payload);

        $retryPolicy->shouldNotReceive('publishRetry');
        $retryPolicy->shouldNotReceive('publishDlq');

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume($this->buildMessage(Carbon::now()->subMinute(), $payload));

        Carbon::setTestNow();
    }

    public function test_sleeps_until_scheduled_when_not_due(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);
        $context = new DryRunContext(new DryRunRecorder());

        $processor->shouldReceive('process')
            ->once();

        $retryPolicy->shouldNotReceive('publishDlq');

        $consumer = $this->getMockBuilder(TransferRetryMessageConsumer::class)
            ->setConstructorArgs([$processor, $retryPolicy, $context])
            ->onlyMethods(['sleepUntilDue'])
            ->getMock();

        $consumer->expects($this->once())
            ->method('sleepUntilDue');

        $consumer->consume($this->buildMessage(Carbon::now()->addMinute()));

        Carbon::setTestNow();
    }

    public function test_sends_missing_meta_to_dlq(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);
        $context = new DryRunContext(new DryRunRecorder());

        $processor->shouldNotReceive('process');

        $retryPolicy->shouldReceive('publishDlq')
            ->once()
            ->with(['payload' => []], 'missing meta');

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume(['payload' => []]);
    }

    public function test_sends_missing_retry_metadata_to_dlq(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);
        $context = new DryRunContext(new DryRunRecorder());

        $processor->shouldNotReceive('process');

        $retryPolicy->shouldReceive('publishDlq')
            ->once()
            ->with(
                $this->anything(),
                'missing retry metadata'
            );

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume([
            'meta' => ['version' => '1.0'],
            'payload' => ['transfer_id' => 'txn_123'],
        ]);
    }

    public function test_retries_on_processor_failure_when_attempt_is_below_limit(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);
        $context = new DryRunContext(new DryRunRecorder());
        $exception = new RuntimeException('processor failed');

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $processor->shouldReceive('process')
            ->once()
            ->with($payload)
            ->andThrow($exception);

        $retryPolicy->shouldReceive('shouldRetry')
            ->once()
            ->with(1)
            ->andReturn(true);

        $retryPolicy->shouldReceive('publishRetry')
            ->once()
            ->with('txn_123', $payload, 1, 'processor failed');

        $retryPolicy->shouldNotReceive('publishDlq');

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume($this->buildMessage(Carbon::now()->subMinute(), $payload, 1));

        Carbon::setTestNow();
    }

    public function test_sends_to_dlq_when_attempt_limit_exhausted(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);
        $context = new DryRunContext(new DryRunRecorder());
        $exception = new RuntimeException('processor failed');

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $message = $this->buildMessage(Carbon::now()->subMinute(), $payload, 3);

        $processor->shouldReceive('process')
            ->once()
            ->with($payload)
            ->andThrow($exception);

        $retryPolicy->shouldReceive('shouldRetry')
            ->once()
            ->with(3)
            ->andReturn(false);

        $retryPolicy->shouldNotReceive('publishRetry');

        $retryPolicy->shouldReceive('publishDlq')
            ->once()
            ->with($message, 'processor failed');

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume($message);

        Carbon::setTestNow();
    }

    public function test_skips_wait_and_records_in_dry_run(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $processor->shouldReceive('process')
            ->once()
            ->with($payload);

        $retryPolicy->shouldNotReceive('publishDlq');

        $consumer = $this->getMockBuilder(TransferRetryMessageConsumer::class)
            ->setConstructorArgs([$processor, $retryPolicy, $context])
            ->onlyMethods(['sleepUntilDue'])
            ->getMock();

        $consumer->expects($this->never())
            ->method('sleepUntilDue');

        $scheduledAt = Carbon::now()->addMinute();
        $consumer->consume($this->buildMessage($scheduledAt, $payload));

        $entries = $context->flush();
        $this->assertCount(1, $entries);
        $this->assertSame('retry.wait_skipped', $entries[0]['action']);
        $this->assertSame($scheduledAt->format(DateTimeInterface::ATOM), $entries[0]['context']['scheduled_at']);

        Carbon::setTestNow();
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function buildMessage(Carbon $scheduledAt, array $payload = [
        'transfer_id' => 'txn_123',
        'payer_id' => 1,
        'payee_id' => 2,
        'amount' => 1000,
    ], int $attempt = 1): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.retry',
                'occurred_at' => Carbon::now()->toIso8601String(),
                'retry' => [
                    'attempt' => $attempt,
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                ],
            ],
            'payload' => $payload,
        ];
    }
}
