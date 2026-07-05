<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TransferMessageConsumer;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;

final class ConsumeTransfersCommand extends Command
{
    protected $signature = 'kafka:consume-transfers {--dry-run : Simulate without committing offsets or side effects} {--daemon : Run continuously in daemon mode with per-topic batch configuration}';

    protected $description = 'Consume transfer events from the Kafka wallet.transfer.completed topic';

    public function __construct(
        private readonly TransferMessageConsumer $consumer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isDaemon = (bool) $this->option('daemon');

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Enabled — no offsets will be committed and no side effects will be persisted.');
        }

        $topic = (string) config('kafka.topic_completed', 'wallet.transfer.completed');
        $consumerGroup = (string) config('kafka.consumer_group_id', 'wallet-transfer-consumers');

        $this->info("Starting Kafka consumer for topic [{$topic}]...");

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Kafka consumer will not commit offsets.');
        }

        $builder = Kafka::consumer([$topic], $consumerGroup)
            ->withManualCommit()
            ->withHandler(function (ConsumerMessage $message, MessageConsumer $consumer) use ($isDryRun): void {
                $body = $message->getBody();
                $payload = $body['payload'] ?? null;
                $transferId = is_array($payload) ? ($payload['transfer_id'] ?? null) : null;
                $transferIdStr = is_int($transferId) || is_string($transferId) ? (string) $transferId : 'unknown';

                $this->info("Kafka message received. [transfer_id={$transferIdStr}]");

                try {
                    $this->consumer->consume($body, $isDryRun);
                    $this->info("Kafka message processed successfully. [transfer_id={$transferIdStr}]");
                } catch (\Throwable $exception) {
                    $this->error("Kafka message processing failed. [transfer_id={$transferIdStr}] [exception={$exception->getMessage()}]");

                    throw $exception;
                }

                if (!$isDryRun) {
                    $consumer->commit($message);
                    $this->info("Kafka message offset committed. [transfer_id={$transferIdStr}]");

                    return;
                }

                $this->warn('[DRY-RUN] Kafka offset commit skipped.');
            });

        if ($isDaemon) {
            $topicConfig = $this->resolveTopicConfig($topic);
            $builder = $builder->withMaxMessages($topicConfig['limit_messages']);
        }

        $builder->build()->consume();

        return self::SUCCESS;
    }

    /**
     * @return array{limit_messages: int}
     */
    private function resolveTopicConfig(string $topic): array
    {
        $config = config("kafka.topics.{$topic}", []);

        if (!is_array($config)) {
            $config = [];
        }

        return [
            'limit_messages' => isset($config['limit_messages']) && is_int($config['limit_messages']) ? $config['limit_messages'] : 100,
        ];
    }
}
