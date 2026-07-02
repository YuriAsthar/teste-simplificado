<?php

declare(strict_types=1);

namespace Tests\Feature\Kafka;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RdKafka;
use Tests\TestCase;

abstract class KafkaTestCase extends TestCase
{
    protected ?string $broker = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broker = (string) ($_ENV['KAFKA_INTEGRATION_BROKER']
            ?? $_SERVER['KAFKA_INTEGRATION_BROKER']
            ?? 'kafka:9092');

        if ($this->broker === '' || $this->broker === '127.0.0.1:9092') {
            $this->markTestSkipped('KAFKA_INTEGRATION_BROKER is not configured for integration tests.');
        }

        $this->waitForBroker(10000);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function produceMessage(string $topic, array $payload, ?string $key = null): void
    {
        $this->createTopicIfNeeded($topic);

        $conf = $this->baseConfig();
        $producer = new RdKafka\Producer($conf);
        $topicHandle = $producer->newTopic($topic);

        $topicHandle->producev(
            RD_KAFKA_PARTITION_UA,
            0,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $key ?? null,
            null,
            0,
        );

        $producer->flush(10000);
    }

    protected function subscribeToEnd(string $topic, string $groupId): RdKafka\KafkaConsumer
    {
        $this->createTopicIfNeeded($topic);

        $conf = $this->baseConfig();
        $conf->set('group.id', $groupId);
        $conf->set('auto.offset.reset', 'latest');
        $conf->set('enable.auto.commit', 'false');

        $consumer = new RdKafka\KafkaConsumer($conf);
        $consumer->subscribe([$topic]);

        $this->drainPartitionEof($consumer, 5000);

        return $consumer;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function consumeNext(RdKafka\KafkaConsumer $consumer, int $timeoutMs = 5000): ?array
    {
        $start = hrtime(true);

        while (true) {
            $elapsedMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($elapsedMs >= $timeoutMs) {
                return null;
            }

            /** @var RdKafka\Message|null $message */
            $message = $consumer->consume($timeoutMs - $elapsedMs);

            if ($message === null) {
                continue;
            }

            if ($message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                continue;
            }

            if ($message->err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                continue;
            }

            return [
                'body' => json_decode($message->payload, true, 512, JSON_THROW_ON_ERROR),
                'key' => $message->key,
                'topic' => $message->topic_name,
                'partition' => $message->partition,
                'offset' => $message->offset,
            ];
        }
    }

    protected function waitForTopic(string $topic, int $timeoutMs = 10000): void
    {
        $start = hrtime(true);

        while (true) {
            $elapsedMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($elapsedMs >= $timeoutMs) {
                $this->markTestSkipped("Kafka broker at {$this->broker} is not reachable or topic [{$topic}] is unavailable.");
            }

            try {
                if ($this->topicExists($topic)) {
                    return;
                }
            } catch (\Exception) {
                // Broker may not be ready yet; retry
            }

            usleep(100_000);
        }
    }

    protected function clearIdempotencyCache(string $transferId): void
    {
        Cache::store(config('kafka.cache_driver'))
            ->forget('kafka:transfer:' . $transferId);
    }

    private function baseConfig(): RdKafka\Conf
    {
        $conf = new RdKafka\Conf();
        $conf->set('metadata.broker.list', $this->broker);
        $conf->set('socket.timeout.ms', '5000');

        return $conf;
    }

    private function drainPartitionEof(RdKafka\KafkaConsumer $consumer, int $timeoutMs): void
    {
        $start = hrtime(true);

        while (true) {
            $elapsedMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($elapsedMs >= $timeoutMs) {
                return;
            }

            /** @var RdKafka\Message|null $message */
            $message = $consumer->consume($timeoutMs - $elapsedMs);

            if ($message === null) {
                return;
            }

            if ($message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                return;
            }
        }
    }

    /** @phpstan-impure */
    private function topicExists(string $topic): bool
    {
        $consumer = new RdKafka\Consumer($this->baseConfig());
        $metadata = $consumer->getMetadata(true, null, 5000);

        foreach ($metadata->getTopics() as $topicMetadata) {
            if ($topicMetadata->getTopic() === $topic) {
                return true;
            }
        }

        return false;
    }

    private function createTopicIfNeeded(string $topic): void
    {
        if ($this->topicExists($topic)) {
            return;
        }

        // Trigger auto-topic creation by producing a single metadata request
        $producer = new RdKafka\Producer($this->baseConfig());
        $producer->poll(0);

        $start = hrtime(true);
        $timeoutMs = 10000;

        while (true) {
            $elapsedMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($elapsedMs >= $timeoutMs) {
                return;
            }

            try {
                $producer->newTopic($topic);

                if ($this->topicExists($topic)) {
                    return;
                }
            } catch (\Exception) {
                // Topic may not exist yet; retry
            }

            usleep(100_000);
        }
    }

    private function waitForBroker(int $timeoutMs): void
    {
        $consumer = new RdKafka\Consumer($this->baseConfig());

        $start = hrtime(true);

        while (true) {
            $elapsedMs = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($elapsedMs >= $timeoutMs) {
                $this->markTestSkipped("Kafka broker at {$this->broker} is not reachable.");
            }

            try {
                $consumer->getMetadata(true, null, 1000);

                return;
            } catch (\Exception) {
                // Broker may not be ready yet; retry
            }

            usleep(100_000);
        }
    }
}
