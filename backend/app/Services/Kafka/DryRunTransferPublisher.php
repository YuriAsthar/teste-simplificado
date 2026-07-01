<?php

declare(strict_types=1);

namespace App\Services\Kafka;

use App\Contracts\TransferPublisherInterface;
use App\Services\DryRun\DryRunContext;

final readonly class DryRunTransferPublisher implements TransferPublisherInterface
{
    public function __construct(
        private DryRunContext $context,
        private TransferPublisherInterface $publisher,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $topic, array $payload, ?string $key = null): void
    {
        if ($this->context->isEnabled()) {
            $this->context->record('kafka.publish', [
                'topic' => $topic,
                'key' => $key,
                'payload' => $payload,
            ]);

            return;
        }

        $this->publisher->publish($topic, $payload, $key);
    }
}
