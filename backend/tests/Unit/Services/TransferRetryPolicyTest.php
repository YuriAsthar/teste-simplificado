<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\TransferPublisherInterface;
use App\DTOs\TransferMessagePayload;
use App\Services\TransferRetryPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class TransferRetryPolicyTest extends TestCase
{
    public function test_should_retry_returns_true_when_below_limit(): void
    {
        $publisher = $this->createFakePublisher();
        $policy = new TransferRetryPolicy($publisher);

        $this->assertTrue($policy->shouldRetry(0));
        $this->assertTrue($policy->shouldRetry(1));
        $this->assertTrue($policy->shouldRetry(2));
    }

    public function test_should_retry_returns_false_when_limit_reached(): void
    {
        $publisher = $this->createFakePublisher();
        $policy = new TransferRetryPolicy($publisher);

        $this->assertFalse($policy->shouldRetry(3));
        $this->assertFalse($policy->shouldRetry(4));
    }

    public function test_publish_retry_publishes_envelope_with_incremented_attempt(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $publisher = $this->createFakePublisher();
        $policy = new TransferRetryPolicy($publisher);

        $payload = TransferMessagePayload::fromArray(['transfer_id' => 123, 'payer_id' => 1]);

        $policy->publishRetry($payload, 1, 'failed');

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.retry', $publisher->published[0]['topic']);
        $this->assertSame('123', $publisher->published[0]['key']);
        $this->assertSame('transfer.retry', $publisher->published[0]['envelope']['meta']['event']);
        $this->assertSame(2, $publisher->published[0]['envelope']['meta']['retry']['attempt']);
        $this->assertSame('failed', $publisher->published[0]['envelope']['meta']['reason']);
        $this->assertSame(123, $publisher->published[0]['envelope']['payload']['transfer_id']);

        Carbon::setTestNow();
    }

    public function test_publish_dlq_publishes_failed_envelope(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $publisher = $this->createFakePublisher();
        $policy = new TransferRetryPolicy($publisher);

        $body = ['transfer_id' => 123];

        $policy->publishDlq($body, 'bad request');

        $this->assertCount(1, $publisher->published);
        $this->assertSame('wallet.transfer.dlq', $publisher->published[0]['topic']);
        $this->assertSame('transfer.failed', $publisher->published[0]['envelope']['meta']['event']);
        $this->assertSame('bad request', $publisher->published[0]['envelope']['meta']['reason']);
        $this->assertSame($body, $publisher->published[0]['envelope']['payload']);

        Carbon::setTestNow();
    }

    private function createFakePublisher(): object
    {
        return new class() implements TransferPublisherInterface {
            /** @var list<array{topic: string, envelope: array<string, mixed>, key: string|null}> */
            public array $published = [];

            public function publish(string $topic, array $payload, ?string $key = null): void
            {
                $this->published[] = [
                    'topic' => $topic,
                    'envelope' => $payload,
                    'key' => $key,
                ];
            }
        };
    }
}
