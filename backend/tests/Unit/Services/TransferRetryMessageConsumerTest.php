<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\TransferPublisherInterface;
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
    public function test_reprocesses_message_when_due(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);
        $context = new DryRunContext(new DryRunRecorder());

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $processor->shouldReceive('process')
            ->once()
            ->with($payload);

        $consumer = new TestableTransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume($this->buildMessage(Carbon::now()->subMinute(), $payload));

        $this->assertCount(0, $publisher->published);

        Carbon::setTestNow();
    }

    public function test_sleeps_until_scheduled_when_not_due(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);
        $context = new DryRunContext(new DryRunRecorder());

        $processor->shouldReceive('process')
            ->once();

        $consumer = new TestableTransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume($this->buildMessage(Carbon::now()->addMinute()));

        $this->assertTrue($consumer->sleepCalled);

        Carbon::setTestNow();
    }

    public function test_sends_missing_meta_to_dlq(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);
        $context = new DryRunContext(new DryRunRecorder());

        $processor->shouldNotReceive('process');

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume(['payload' => []]);

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.dlq', $publisher->published[0]['topic']);
        $this->assertSame('missing meta', $publisher->published[0]['envelope']['meta']['reason']);
    }

    public function test_sends_missing_retry_metadata_to_dlq(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);
        $context = new DryRunContext(new DryRunRecorder());

        $processor->shouldNotReceive('process');

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume([
            'meta' => ['version' => '1.0'],
            'payload' => ['transfer_id' => '123'],
        ]);

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.dlq', $publisher->published[0]['topic']);
        $this->assertSame('missing retry metadata', $publisher->published[0]['envelope']['meta']['reason']);
    }

    public function test_retries_on_processor_failure_when_attempt_is_below_limit(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);
        $context = new DryRunContext(new DryRunRecorder());
        $exception = new RuntimeException('processor failed');

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $processor->shouldReceive('process')
            ->once()
            ->with($payload)
            ->andThrow($exception);

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume($this->buildMessage(Carbon::now()->subMinute(), $payload, 1));

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.retry', $publisher->published[0]['topic']);
        $this->assertSame(2, $publisher->published[0]['envelope']['meta']['retry']['attempt']);
        $this->assertSame('processor failed', $publisher->published[0]['envelope']['meta']['reason']);
        $this->assertSame('123', $publisher->published[0]['envelope']['payload']['transfer_id']);

        Carbon::setTestNow();
    }

    public function test_sends_to_dlq_when_attempt_limit_exhausted(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);
        $context = new DryRunContext(new DryRunRecorder());
        $exception = new RuntimeException('processor failed');

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $message = $this->buildMessage(Carbon::now()->subMinute(), $payload, 3);

        $processor->shouldReceive('process')
            ->once()
            ->with($payload)
            ->andThrow($exception);

        $consumer = new TransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $consumer->consume($message);

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.dlq', $publisher->published[0]['topic']);
        $this->assertSame('processor failed', $publisher->published[0]['envelope']['meta']['reason']);

        Carbon::setTestNow();
    }

    public function test_skips_wait_and_records_in_dry_run(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $processor->shouldReceive('process')
            ->once()
            ->with($payload);

        $consumer = new TestableTransferRetryMessageConsumer($processor, $retryPolicy, $context);
        $scheduledAt = Carbon::now()->addMinute();
        $consumer->consume($this->buildMessage($scheduledAt, $payload));

        $this->assertFalse($consumer->sleepCalled);

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
        'transfer_id' => '123',
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

    private function createFakePublisher(): object
    {
        return new class() implements TransferPublisherInterface {
            /** @var list<array{topic: string, envelope: array<string, mixed>, key: string|null}> */
            public array $published = [];

            public function publish(string $topic, array $payload, ?string $key = null): void
            {
                $this->published[] = [
                    'topic' => $topic,
                    'envelope' => $payload,
                    'key' => $key,
                ];
            }
        };
    }
}

class TestableTransferRetryMessageConsumer extends TransferRetryMessageConsumer
{
    public bool $sleepCalled = false;

    protected function sleepUntilDue(\DateTimeInterface $scheduledAt): void
    {
        $this->sleepCalled = true;
    }
}
