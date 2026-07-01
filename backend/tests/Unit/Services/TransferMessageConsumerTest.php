<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\TransferPublisherInterface;
use App\Services\DryRun\DryRunContext;
use App\Services\DryRun\DryRunRecorder;
use App\Services\Kafka\DryRunTransferPublisher;
use App\Services\KafkaTransferProcessor;
use App\Services\TransferMessageConsumer;
use App\Services\TransferRetryPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class TransferMessageConsumerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_consumes_valid_transfer_and_dispatches_notification(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);

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

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
            ],
            'payload' => $payload,
        ]);
    }

    public function test_sends_malformed_message_to_dlq(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
            ],
            'payload' => [
                'payer_id' => 5,
                'payee_id' => 6,
                'amount' => 1000,
            ],
        ];

        $processor->shouldNotReceive('process');

        $retryPolicy->shouldReceive('publishDlq')
            ->once()
            ->with($message, 'missing transfer_id');

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume($message);
    }

    public function test_publishes_retry_on_processor_failure(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $exception = new RuntimeException('processor failed');

        $processor->shouldReceive('process')
            ->once()
            ->with($payload)
            ->andThrow($exception);

        $retryPolicy->shouldReceive('shouldRetry')
            ->once()
            ->with(0)
            ->andReturn(true);

        $retryPolicy->shouldReceive('publishRetry')
            ->once()
            ->with('txn_123', $payload, 0, 'processor failed');

        $retryPolicy->shouldNotReceive('publishDlq');

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
            ],
            'payload' => $payload,
        ]);
    }

    public function test_publishes_retry_with_existing_attempt_count(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $exception = new RuntimeException('processor failed');

        $processor->shouldReceive('process')
            ->once()
            ->with($payload)
            ->andThrow($exception);

        $retryPolicy->shouldReceive('shouldRetry')
            ->once()
            ->with(2)
            ->andReturn(true);

        $retryPolicy->shouldReceive('publishRetry')
            ->once()
            ->with('txn_123', $payload, 2, 'processor failed');

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
                'retry' => ['attempt' => 2],
            ],
            'payload' => $payload,
        ]);
    }

    public function test_sends_to_dlq_when_retry_attempts_exhausted(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
                'retry' => ['attempt' => 3],
            ],
            'payload' => $payload,
        ];

        $exception = new RuntimeException('processor failed');

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

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume($message);
    }

    public function test_publishes_dlq_for_invalid_transfer_id_payload(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $retryPolicy = $this->mock(TransferRetryPolicy::class);

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
            ],
            'payload' => [
                'transfer_id' => '',
                'payer_id' => 1,
                'payee_id' => 2,
                'amount' => 1000,
            ],
        ];

        $processor->shouldNotReceive('process');

        $retryPolicy->shouldReceive('publishDlq')
            ->once()
            ->with($message, 'missing transfer_id');

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume($message);
    }

    public function test_records_retry_publish_in_dry_run(): void
    {
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $publisher = $this->createMock(TransferPublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $retryPolicy = new TransferRetryPolicy(new DryRunTransferPublisher($context, $publisher));

        $processor = $this->mock(KafkaTransferProcessor::class);

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $exception = new RuntimeException('processor failed');

        $processor->shouldReceive('process')
            ->once()
            ->with($payload)
            ->andThrow($exception);

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
            ],
            'payload' => $payload,
        ]);

        $entries = $context->flush();
        $this->assertCount(1, $entries);
        $this->assertSame('kafka.publish', $entries[0]['action']);
        $this->assertSame('wallet.transfer.retry', $entries[0]['context']['topic']);
        $this->assertSame('txn_123', $entries[0]['context']['key']);
    }

    public function test_records_dlq_publish_in_dry_run(): void
    {
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $publisher = $this->createMock(TransferPublisherInterface::class);
        $publisher->expects($this->never())->method('publish');

        $retryPolicy = new TransferRetryPolicy(new DryRunTransferPublisher($context, $publisher));

        $processor = $this->mock(KafkaTransferProcessor::class);

        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
                'retry' => ['attempt' => 3],
            ],
            'payload' => $payload,
        ];

        $exception = new RuntimeException('processor failed');

        $processor->shouldReceive('process')
            ->once()
            ->with($payload)
            ->andThrow($exception);

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume($message);

        $entries = $context->flush();
        $this->assertCount(1, $entries);
        $this->assertSame('kafka.publish', $entries[0]['action']);
        $this->assertSame('wallet.transfer.dlq', $entries[0]['context']['topic']);
    }
}
