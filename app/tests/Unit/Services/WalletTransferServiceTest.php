<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Enums\UserType;
use App\Jobs\SendTransferNotificationJob;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuthorizerClient;
use App\Services\WalletTransferService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

final class WalletTransferServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Mockery\MockInterface $authorizer;

    private Mockery\MockInterface $dispatcher;

    private Mockery\MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizer = Mockery::mock(AuthorizerClient::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_completes_transfer_between_common_users(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        $this->authorizer->shouldReceive('authorize')
            ->once()
            ->andReturn(true);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(static fn (SendTransferNotificationJob $job): bool => $job->transferId > 0));

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->logger,
        );

        $transfer = $service->execute($payer->id, $payee->id, 2500, 'key-1');

        $this->assertSame(TransferStatus::Completed, $transfer->status);
        $this->assertSame(7500, (int) $payer->fresh()?->wallet->getRawOriginal('balance'));
        $this->assertSame(2500, (int) $payee->fresh()?->wallet->getRawOriginal('balance'));
    }

    public function test_it_fails_when_payer_is_merchant(): void
    {
        $merchant = User::factory()->create(['type' => UserType::Merchant->value]);
        $payee = User::factory()->create();

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->logger,
        );

        $transfer = $service->execute($merchant->id, $payee->id, 1000, 'key-2');

        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertSame(FailureReason::PayerIsMerchant, $transfer->failure_reason);
    }

    public function test_it_fails_when_payer_wallet_is_soft_deleted(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->delete();

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->logger,
        );

        $transfer = $service->execute($payer->id, $payee->id, 1000, 'key-3');

        $this->assertSame(FailureReason::WalletInactive, $transfer->failure_reason);
    }

    public function test_it_fails_when_currencies_mismatch(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();
        $payee->wallet->update(['currency' => CurrencyType::USD->value]);

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->logger,
        );

        $transfer = $service->execute($payer->id, $payee->id, 1000, 'key-4');

        $this->assertSame(FailureReason::CurrencyMismatch, $transfer->failure_reason);
    }

    public function test_it_fails_when_authorizer_rejects(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        $this->authorizer->shouldReceive('authorize')
            ->once()
            ->andReturn(false);

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->logger,
        );

        $transfer = $service->execute($payer->id, $payee->id, 1000, 'key-5');

        $this->assertSame(FailureReason::AuthorizerRejected, $transfer->failure_reason);
    }

    public function test_it_returns_existing_transfer_for_duplicate_idempotency_key(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        $this->authorizer->shouldReceive('authorize')
            ->once()
            ->andReturn(true);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(static fn (SendTransferNotificationJob $job): bool => $job->transferId > 0));

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->logger,
        );

        $first = $service->execute($payer->id, $payee->id, 1000, 'duplicate-key');
        $second = $service->execute($payer->id, $payee->id, 1000, 'duplicate-key');

        $this->assertSame($first->id, $second->id);
    }
}
