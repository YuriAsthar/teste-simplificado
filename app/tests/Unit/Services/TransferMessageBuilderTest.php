<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TransferMessageBuilder;
use Tests\TestCase;

final class TransferMessageBuilderTest extends TestCase
{
    public function test_build_returns_topic_key_and_envelope(): void
    {
        $builder = new TransferMessageBuilder();
        $payload = [
            'transfer_id' => 'txn_123',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
            'occurred_at' => now()->toIso8601String(),
        ];

        $message = $builder->build($payload);

        $this->assertSame('wallet.transfer.completed', $message['topic']);
        $this->assertSame('txn_123', $message['key']);
        $this->assertArrayHasKey('meta', $message['envelope']);
        $this->assertArrayHasKey('payload', $message['envelope']);
        $this->assertSame('1.0', $message['envelope']['meta']['version']);
        $this->assertSame('transfer.authorized', $message['envelope']['meta']['event']);
        $this->assertSame('txn_123', $message['envelope']['payload']['transfer_id']);
        $this->assertSame(1, $message['envelope']['payload']['payer_id']);
        $this->assertSame(2, $message['envelope']['payload']['payee_id']);
        $this->assertSame(1000, $message['envelope']['payload']['amount']);
    }

    public function test_get_topic_returns_wallet_transfer_completed(): void
    {
        $builder = new TransferMessageBuilder();

        $this->assertSame('wallet.transfer.completed', $builder->getTopic());
    }
}
