<?php

declare(strict_types=1);

namespace App\Services;

final readonly class TransferMessageBuilder
{
    private const string EVENT_NAME = 'transfer.authorized';

    private const string VERSION = '1.0';

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{topic: string, key: string, envelope: array<string, mixed>}
     */
    public function build(array $payload): array
    {
        $transferId = (string) ($payload['transfer_id'] ?? '');

        return [
            'topic' => $this->getTopic(),
            'key' => $transferId,
            'envelope' => [
                'meta' => [
                    'version' => self::VERSION,
                    'event' => self::EVENT_NAME,
                    'occurred_at' => now()->toIso8601String(),
                ],
                'payload' => $payload,
            ],
        ];
    }

    public function getTopic(): string
    {
        return (string) config('kafka.topic_completed', 'wallet.transfer.completed');
    }
}
