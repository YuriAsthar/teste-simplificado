<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TransferPublisherInterface;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

final readonly class KafkaTransferPublisher implements TransferPublisherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $topic, array $payload, ?string $key = null): void
    {
        $message = (new Message(topicName: $topic))
            ->withBody($payload);

        if (!is_null($key) && $key !== '') {
            $message = $message->withKey($key);
        }

        Kafka::publish()
            ->onTopic($topic)
            ->withMessage($message)
            ->send();
    }
}
