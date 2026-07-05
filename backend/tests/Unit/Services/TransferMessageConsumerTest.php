<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\TransferPublisherInterface;
use App\DTOs\TransferMessagePayload;
use App\Services\KafkaTransferProcessor;
use App\Services\TransferMessageConsumer;
use App\Services\TransferRetryPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

final class TransferMessageConsumerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_consumes_valid_transfer_and_dispatches_notification(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $processor->shouldReceive('process')
            ->once()
            ->with($this->callback(static function (TransferMessagePayload $dto): bool {
                return $dto->transferId === 123
                    && $dto->payerId === 1
                    && $dto->payeeId === 2;
            }), false);

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
            ],
            'payload' => $payload,
        ]);

        $this->assertCount(0, $publisher->published);
    }

    public function test_sends_malformed_message_to_dlq(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
            ],
            'payload' => [
                'payer_id' => 5,
                'payee_id' => 6,
                'amount' => 1000,
            ],
        ];

        $processor->shouldNotReceive('process');

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume($message);

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.dlq', $publisher->published[0]['topic']);
        $this->assertSame('missing transfer_id', $publisher->published[0]['envelope']['meta']['reason']);
    }

    public function test_publishes_retry_on_processor_failure(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $exception = new RuntimeException('processor failed');

        $processor->shouldReceive('process')
            ->once()
            ->with($this->callback(static function (TransferMessagePayload $dto): bool {
                return $dto->transferId === 123
                    && $dto->payerId === 1
                    && $dto->payeeId === 2;
            }), false)
            ->andThrow($exception);

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
            ],
            'payload' => $payload,
        ]);

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.retry', $publisher->published[0]['topic']);
        $this->assertSame('123', $publisher->published[0]['key']);
        $this->assertSame(1, $publisher->published[0]['envelope']['meta']['retry']['attempt']);
        $this->assertSame('processor failed', $publisher->published[0]['envelope']['meta']['reason']);
        $this->assertSame(123, $publisher->published[0]['envelope']['payload']['transfer_id']);
    }

    public function test_publishes_retry_with_existing_attempt_count(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $exception = new RuntimeException('processor failed');

        $processor->shouldReceive('process')
            ->once()
            ->with($this->callback(static function (TransferMessagePayload $dto): bool {
                return $dto->transferId === 123
                    && $dto->payerId === 1
                    && $dto->payeeId === 2;
            }), false)
            ->andThrow($exception);

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
                'retry' => ['attempt' => 2],
            ],
            'payload' => $payload,
        ]);

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.retry', $publisher->published[0]['topic']);
        $this->assertSame(3, $publisher->published[0]['envelope']['meta']['retry']['attempt']);
        $this->assertSame(123, $publisher->published[0]['envelope']['payload']['transfer_id']);
    }

    public function test_sends_to_dlq_when_retry_attempts_exhausted(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
                'retry' => ['attempt' => 3],
            ],
            'payload' => $payload,
        ];

        $exception = new RuntimeException('processor failed');

        $processor->shouldReceive('process')
            ->once()
            ->with($this->callback(static function (TransferMessagePayload $dto): bool {
                return $dto->transferId === 123
                    && $dto->payerId === 1
                    && $dto->payeeId === 2;
            }), false)
            ->andThrow($exception);

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume($message);

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.dlq', $publisher->published[0]['topic']);
        $this->assertSame('processor failed', $publisher->published[0]['envelope']['meta']['reason']);
        $this->assertSame($message, $publisher->published[0]['envelope']['payload']);
    }

    public function test_publishes_dlq_for_invalid_transfer_id_payload(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
            ],
            'payload' => [
                'transfer_id' => '',
                'payer_id' => 1,
                'payee_id' => 2,
                'amount' => 1000,
            ],
        ];

        $processor->shouldNotReceive('process');

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume($message);

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.dlq', $publisher->published[0]['topic']);
        $this->assertSame('missing transfer_id', $publisher->published[0]['envelope']['meta']['reason']);
    }

    public function test_skips_dlq_publish_in_dry_run(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);

        $processor->shouldNotReceive('process');

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
            ],
            'payload' => [
                'payer_id' => 5,
                'payee_id' => 6,
                'amount' => 1000,
            ],
        ], true);

        $this->assertCount(0, $publisher->published);
    }

    public function test_skips_retry_publish_in_dry_run(): void
    {
        $processor = $this->mock(KafkaTransferProcessor::class);
        $publisher = $this->createFakePublisher();
        $retryPolicy = new TransferRetryPolicy($publisher);

        $payload = [
            'transfer_id' => '123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ];

        $exception = new RuntimeException('processor failed');

        $processor->shouldReceive('process')
            ->once()
            ->with($this->callback(static function (TransferMessagePayload $dto): bool {
                return $dto->transferId === 123
                    && $dto->payerId === 1
                    && $dto->payeeId === 2;
            }), true)
            ->andThrow($exception);

        $consumer = new TransferMessageConsumer($processor, $retryPolicy);
        $consumer->consume([
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
            ],
            'payload' => $payload,
        ], true);

        $this->assertCount(0, $publisher->published);
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
