<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\TransferPublisherInterface;
use App\Jobs\SendNotificationJob;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TransferServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TransferService $transferService;

    private Repository $cache;

    private Dispatcher $dispatcher;

    private TransferPublisherInterface $publisher;

    private User $user;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->mock(Repository::class);
        $this->dispatcher = $this->mock(Dispatcher::class);
        $this->publisher = $this->mock(TransferPublisherInterface::class);
        $this->user = new User();
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->transferService = new TransferService(
            $this->cache,
            $this->dispatcher,
            $this->publisher,
            $this->user,
            $this->logger,
        );
    }

    public function test_get_cached_user_remembers_user_for_sixty_seconds(): void
    {
        $user = User::factory()->create();

        $this->cache
            ->shouldReceive('remember')
            ->twice()
            ->withArgs(static fn (string $key, int $ttl, $callback): bool => $key === "user:{$user->id}" && $ttl === 60)
            ->andReturn($user);

        $first = $this->transferService->getCachedUser($user->id);
        $second = $this->transferService->getCachedUser($user->id);

        $this->assertSame($first, $second);
        $this->assertSame($user->id, $first?->id);
    }

    public function test_authorize_and_execute_transfer_invalidates_payer_and_payee_cache(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $this->cache->shouldReceive('remember')->andReturn($payer, $payee);

        $this->publisher
            ->shouldReceive('publish')
            ->withArgs(static fn (string $topic, array $payload): bool => $topic === 'transfers'
                && str_starts_with($payload['transfer_id'], 'txn_')
                && $payload['payer_id'] === $payer->id
                && $payload['payee_id'] === $payee->id
                && $payload['amount_cents'] === 2500);

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->withArgs(static fn (SendNotificationJob $job): bool => $job->payerId === $payer->id
                && $job->payeeId === $payee->id
                && $job->amountCents === 2500
                && str_starts_with($job->transferId, 'txn_'))
            ->andReturnSelf();

        $this->dispatcher->shouldReceive('onConnection')->with('rabbitmq')->andReturnSelf();

        $this->cache->shouldReceive('forget')->with("user:{$payer->id}");
        $this->cache->shouldReceive('forget')->with("user:{$payee->id}");

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Transfer authorized', $this->anything());

        $result = $this->transferService->authorizeAndExecuteTransfer(
            $payer->id,
            $payee->id,
            2500,
        );

        $this->assertSame('authorized', $result['status']);
        $this->assertTrue(str_starts_with($result['transfer_id'], 'txn_'));
    }

    public function test_authorize_and_execute_transfer_rejects_missing_payer(): void
    {
        $payee = User::factory()->create();

        $this->cache->shouldReceive('remember')->andReturn(null, $payee);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payer and payee must exist.');

        $this->transferService->authorizeAndExecuteTransfer(99999, $payee->id, 1000);
    }

    public function test_authorize_and_execute_transfer_rejects_non_positive_amount(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $this->cache->shouldReceive('remember')->andReturn($payer, $payee);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero.');

        $this->transferService->authorizeAndExecuteTransfer($payer->id, $payee->id, 0);
    }
}
