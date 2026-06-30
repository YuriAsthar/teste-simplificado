<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Kafka;

use App\Contracts\TransferPublisherInterface;
use App\Services\DryRun\DryRunContext;
use App\Services\DryRun\DryRunRecorder;
use App\Services\Kafka\DryRunTransferPublisher;
use PHPUnit\Framework\TestCase;

final class DryRunTransferPublisherTest extends TestCase
{
    public function test_delegates_when_context_disabled(): void
    {
        $context = new DryRunContext(new DryRunRecorder());
        $publisher = $this->createMock(TransferPublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with('wallet.transfer.completed', ['foo' => 'bar'], 'txn_123');

        $decorator = new DryRunTransferPublisher($context, $publisher);
        $decorator->publish('wallet.transfer.completed', ['foo' => 'bar'], 'txn_123');
    }

    public function test_records_when_context_enabled(): void
    {
        $context = new DryRunContext(new DryRunRecorder());
        $context->enable();

        $publisher = $this->createMock(TransferPublisherInterface::class);
        $publisher->expects($this->never())
            ->method('publish');

        $decorator = new DryRunTransferPublisher($context, $publisher);
        $decorator->publish('wallet.transfer.completed', ['foo' => 'bar'], 'txn_123');

        $entries = $context->flush();

        $this->assertCount(1, $entries);
        $this->assertSame('kafka.publish', $entries[0]['action']);
        $this->assertSame('wallet.transfer.completed', $entries[0]['context']['topic']);
        $this->assertSame('txn_123', $entries[0]['context']['key']);
        $this->assertSame(['foo' => 'bar'], $entries[0]['context']['payload']);
    }

    public function test_implements_transfer_publisher_interface(): void
    {
        $context = new DryRunContext(new DryRunRecorder());
        $publisher = new class() implements TransferPublisherInterface {
            public function publish(string $topic, array $payload, ?string $key = null): void
            {
            }
        };

        $decorator = new DryRunTransferPublisher($context, $publisher);

        $this->assertInstanceOf(TransferPublisherInterface::class, $decorator);
    }
}
