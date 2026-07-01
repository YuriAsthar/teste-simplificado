<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\TransferPublisherInterface;
use App\Enums\TransferStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Transfer;
use App\Models\User;
use App\Services\KafkaTransferService;
use App\Services\TransferMessageBuilder;
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
final class KafkaTransferServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private KafkaTransferService $transferService;

    private Repository $cache;

    private Dispatcher $dispatcher;

    private TransferPublisherInterface $publisher;

    private User $user;

    private TransferMessageBuilder $messageBuilder;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->mock(Repository::class);
        $this->dispatcher = $this->mock(Dispatcher::class);
        $this->publisher = $this->mock(TransferPublisherInterface::class);
        $this->user = new User();
        $this->messageBuilder = new TransferMessageBuilder();
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->transferService = new KafkaTransferService(
            $this->cache,
            $this->dispatcher,
            $this->publisher,
            $this->messageBuilder,
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
        $transfer = Transfer::factory()->create([
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount' => 2500,
            'status' => \App\Enums\TransferStatus::Completed,
        ]);

        $this->cache->shouldReceive('remember')->andReturn($payer, $payee);

        $this->publisher
            ->shouldReceive('publish')
            ->withArgs(static fn (string $topic, array $envelope, ?string $key): bool => $topic === 'wallet.transfer.completed'
                && $key === (string) $transfer->id
                && isset($envelope['meta'], $envelope['payload'])
                && $envelope['meta']['event'] === 'transfer.completed'
                && $envelope['meta']['version'] === '1.0'
                && is_string($envelope['meta']['occurred_at'])
                && $envelope['payload']['transfer_id'] === $transfer->id
                && $envelope['payload']['payer_id'] === $payer->id
                && $envelope['payload']['payee_id'] === $payee->id
                && $envelope['payload']['amount'] === 2500);

        $this->dispatcher
            ->shouldReceive('dispatch')
            ->withArgs(static fn (\App\Jobs\SendNotificationJob $job): bool => $job->transferId === $transfer->id)
            ->andReturnSelf();

        $this->dispatcher->shouldReceive('onConnection')->with('rabbitmq')->andReturnSelf();

        $this->cache->shouldReceive('forget')->with("user:{$payer->id}");
        $this->cache->shouldReceive('forget')->with("user:{$payee->id}");

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Transfer completed event processed', $this->anything());

        $result = $this->transferService->authorizeAndExecuteTransfer(
            $payer->id,
            $payee->id,
            2500,
            $transfer,
        );

        $this->assertSame('completed', $result['status']);
        $this->assertSame($transfer->id, $result['transfer_id']);
    }

    public function test_authorize_and_execute_transfer_rejects_missing_payer(): void
    {
        $payee = User::factory()->create();
        $transfer = Transfer::factory()->create([
            'payer_id' => 99999,
            'payee_id' => $payee->id,
            'amount' => 1000,
            'status' => \App\Enums\TransferStatus::Completed,
        ]);

        $this->cache->shouldReceive('remember')->andReturn(null, $payee);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payer and payee must exist.');

        $this->transferService->authorizeAndExecuteTransfer(99999, $payee->id, 1000, $transfer);
    }

    public function test_authorize_and_execute_transfer_rejects_non_positive_amount(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $transfer = Transfer::factory()->create([
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount' => 1000,
            'status' => \App\Enums\TransferStatus::Completed,
        ]);

        $this->cache->shouldReceive('remember')->andReturn($payer, $payee);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero.');

        $this->transferService->authorizeAndExecuteTransfer($payer->id, $payee->id, 0, $transfer);
    }
}
