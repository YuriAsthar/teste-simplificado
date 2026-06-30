<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DryRun\DryRunContext;
use App\Services\TransferRetryMessageConsumer;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;

final class ConsumeRetryTransfersCommand extends Command
{
    protected $signature = 'kafka:consume-retry-transfers {--dry-run : Simulate without committing offsets or side effects}';

    protected $description = 'Consume delayed retry transfer events from the Kafka wallet.transfer.retry topic';

    public function __construct(
        private readonly TransferRetryMessageConsumer $consumer,
        private readonly DryRunContext $context,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->context->disable();
        if ($isDryRun) {
            $this->context->enable();
            $this->warn('[DRY-RUN] Enabled — no offsets will be committed and no side effects will be persisted.');
        }

        $topic = (string) config('kafka.topic_retry', 'wallet.transfer.retry');
        $consumerGroup = (string) config('kafka.consumer_group_id_retry', 'wallet-transfer-consumers-retry');
        $commitAfterHandle = !$isDryRun && (bool) config('kafka.commit_after_handle', true);

        $this->info("Starting Kafka retry consumer for topic [{$topic}]...");

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Kafka retry consumer will not commit offsets.');
        }

        /** @var MessageConsumer|null $kafkaConsumer */
        $kafkaConsumer = null;

        $builder = Kafka::consumer([$topic], $consumerGroup)
            ->withHandler(function (ConsumerMessage $message) use ($commitAfterHandle, &$kafkaConsumer): void {
                $this->consumer->consume($message->getBody());

                if ($commitAfterHandle && !is_null($kafkaConsumer)) {
                    $kafkaConsumer->commit($message);
                }

                if ($this->context->isEnabled()) {
                    $this->flushDryRunOutput();
                }
            });

        $builder = $builder->withManualCommit();

        if (!$isDryRun && !$commitAfterHandle) {
            $builder = $builder->withAutoCommit();
        }

        $kafkaConsumer = $builder->build();

        $kafkaConsumer->consume();

        return self::SUCCESS;
    }

    private function flushDryRunOutput(): void
    {
        foreach ($this->context->flush() as $entry) {
            $summary = json_encode($entry['context'], JSON_THROW_ON_ERROR);
            $this->warn("[DRY-RUN] {$entry['action']}: {$summary}");
        }
    }
}
