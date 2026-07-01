<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\TransferPublisherInterface;
use App\Services\TransferRetryPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class TransferRetryPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_should_retry_returns_true_when_below_limit(): void
    {
        $publisher = $this->mock(TransferPublisherInterface::class);
        $policy = new TransferRetryPolicy($publisher);

        $this->assertTrue($policy->shouldRetry(0));
        $this->assertTrue($policy->shouldRetry(1));
        $this->assertTrue($policy->shouldRetry(2));
    }

    public function test_should_retry_returns_false_when_limit_reached(): void
    {
        $publisher = $this->mock(TransferPublisherInterface::class);
        $policy = new TransferRetryPolicy($publisher);

        $this->assertFalse($policy->shouldRetry(3));
        $this->assertFalse($policy->shouldRetry(4));
    }

    public function test_publish_retry_publishes_envelope_with_incremented_attempt(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $publisher = $this->mock(TransferPublisherInterface::class);
        $policy = new TransferRetryPolicy($publisher);

        $payload = ['transfer_id' => 'txn_123', 'payer_id' => 1];

        $publisher->shouldReceive('publish')
            ->once()
            ->with(
                'wallet.transfer.retry',
                $this->callback(static function (array $envelope): bool {
                    return $envelope['meta']['event'] === 'transfer.retry'
                        && $envelope['meta']['retry']['attempt'] === 2
                        && $envelope['meta']['reason'] === 'failed'
                        && $envelope['payload']['transfer_id'] === 'txn_123';
                }),
                'txn_123'
            );

        $policy->publishRetry('txn_123', $payload, 1, 'failed');

        Carbon::setTestNow();
    }

    public function test_publish_dlq_publishes_failed_envelope(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $publisher = $this->mock(TransferPublisherInterface::class);
        $policy = new TransferRetryPolicy($publisher);

        $body = ['transfer_id' => 'txn_123'];

        $publisher->shouldReceive('publish')
            ->once()
            ->with(
                'wallet.transfer.dlq',
                $this->callback(static function (array $envelope) use ($body): bool {
                    return $envelope['meta']['event'] === 'transfer.failed'
                        && $envelope['meta']['reason'] === 'bad request'
                        && $envelope['payload'] === $body;
                })
            );

        $policy->publishDlq($body, 'bad request');

        Carbon::setTestNow();
    }
}
