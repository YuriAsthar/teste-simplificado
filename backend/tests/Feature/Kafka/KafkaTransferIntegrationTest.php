<?php

declare(strict_types=1);

namespace Tests\Feature\Kafka;

use App\Contracts\TransferPublisherInterface;
use App\Services\TransferMessageBuilder;
use Illuminate\Support\Facades\Cache;
use RdKafka;

final class KafkaTransferIntegrationTest extends KafkaTestCase
{
    private TransferMessageBuilder $messageBuilder;

    private const string TOPIC = 'wallet.transfer.completed';

    private const string DLQ_TOPIC = 'wallet.transfer.dlq';

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBuilder = new TransferMessageBuilder();
    }

    public function test_produced_message_has_transfer_id_key_and_envelope(): void
    {
        $consumer = $this->subscribeToEnd(self::TOPIC, 'test-produce-consume-' . uniqid());

        $transferId = 123;
        $payload = [
            'transfer_id' => $transferId,
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 5000,
            'occurred_at' => now()->toIso8601String(),
        ];

        $message = $this->messageBuilder->build($payload);
        $this->produceMessage($message['topic'], $message['envelope'], $message['key']);

        $consumed = $this->consumeNext($consumer, 10000);
        $consumer->close();

        $this->assertNotNull($consumed, 'Expected a message on the topic');
        $this->assertSame('123', $consumed['key']);
        $this->assertSame(self::TOPIC, $consumed['topic']);
        $this->assertArrayHasKey('meta', $consumed['body']);
        $this->assertArrayHasKey('payload', $consumed['body']);
        $this->assertSame('1.0', $consumed['body']['meta']['version']);
        $this->assertSame('transfer.completed', $consumed['body']['meta']['event']);
        $this->assertSame($transferId, $consumed['body']['payload']['transfer_id']);
        $this->assertSame(1, $consumed['body']['payload']['payer_id']);
        $this->assertSame(2, $consumed['body']['payload']['payee_id']);
        $this->assertSame(5000, $consumed['body']['payload']['amount']);
    }

    public function test_consumer_skips_duplicate_transfer_id(): void
    {
        $transferId = 456;
        $payload = [
            'transfer_id' => $transferId,
            'payer_id' => 3,
            'payee_id' => 4,
            'amount' => 2500,
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->clearIdempotencyCache((string) $transferId);
        $this->assertFalse(
            Cache::store(config('kafka.cache_driver'))->has('kafka:transfer:' . $transferId),
            'Idempotency key should not exist before processing'
        );

        $message = $this->messageBuilder->build($payload);

        $consumer = $this->subscribeToEnd(self::TOPIC, 'test-duplicate-' . $transferId);
        $this->produceMessage($message['topic'], $message['envelope'], $message['key']);

        $first = $this->consumeNext($consumer, 10000);
        $this->assertNotNull($first);
        $this->assertSame($transferId, $first['body']['payload']['transfer_id']);

        Cache::store(config('kafka.cache_driver'))
            ->put('kafka:transfer:' . $transferId, true, 3600);

        $this->assertTrue(
            Cache::store(config('kafka.cache_driver'))->has('kafka:transfer:' . $transferId),
            'Idempotency key should exist after first processing'
        );

        $this->produceMessage($message['topic'], $message['envelope'], $message['key']);

        $this->assertTrue(
            Cache::store(config('kafka.cache_driver'))->has('kafka:transfer:' . $transferId),
            'Duplicate transfer_id should still be flagged as already processed'
        );

        $consumer->close();
    }

    public function test_failed_message_is_sent_to_dlq(): void
    {
        $malformed = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
                'occurred_at' => now()->toIso8601String(),
            ],
            'payload' => [
                'payer_id' => 5,
                'payee_id' => 6,
                'amount' => 1000,
            ],
        ];

        $consumer = $this->subscribeToEnd(self::TOPIC, 'test-dlq-source-' . uniqid());
        $this->produceMessage(self::TOPIC, $malformed, 'invalid-key');

        $consumed = $this->consumeNext($consumer, 10000);
        $consumer->close();

        $this->assertNotNull($consumed);

        $dlqConsumer = $this->subscribeToEnd(self::DLQ_TOPIC, 'test-dlq-consumer-' . uniqid());

        $dlqEnvelope = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.failed',
                'occurred_at' => now()->toIso8601String(),
                'reason' => 'missing transfer_id',
            ],
            'payload' => $consumed['body'],
        ];

        $this->produceMessage(self::DLQ_TOPIC, $dlqEnvelope, 'invalid-key');

        $dlqMessage = $this->consumeNext($dlqConsumer, 10000);
        $dlqConsumer->close();

        $this->assertNotNull($dlqMessage, 'Expected message on DLQ topic');
        $this->assertSame(self::DLQ_TOPIC, $dlqMessage['topic']);
        $this->assertSame('transfer.failed', $dlqMessage['body']['meta']['event']);
        $this->assertSame('missing transfer_id', $dlqMessage['body']['meta']['reason']);
        $this->assertArrayHasKey('payload', $dlqMessage['body']);
    }
}
