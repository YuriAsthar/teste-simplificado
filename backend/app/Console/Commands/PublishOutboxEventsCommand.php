<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\TransferPublisherInterface;
use App\Models\OutboxEvent;
use App\Services\TransferMessageBuilder;
use Illuminate\Console\Command;
use Throwable;

final class PublishOutboxEventsCommand extends Command
{
    protected $signature = 'outbox:publish {--batch=100 : Number of events to process per run}';
    protected $description = 'Publish pending outbox events to Kafka';

    public function __construct(
        private TransferPublisherInterface $publisher,
        private TransferMessageBuilder $messageBuilder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $events = OutboxEvent::pending()
            ->orderBy('created_at')
            ->limit($batchSize)
            ->get();

        foreach ($events as $event) {
            try {
                /** @var array<string, mixed> $payload */
                $payload = $event->payload;
                $message = $this->messageBuilder->build($payload);
                $this->publisher->publish($message['topic'], $message['envelope'], $message['key']);
                $event->markPublished();
            } catch (Throwable $exception) {
                $event->markFailed();
                $this->error("Failed to publish outbox event {$event->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Processed {$events->count()} outbox events.");

        return self::SUCCESS;
    }
}
