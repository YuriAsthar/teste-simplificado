<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AuthorizerResult;
use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\IdempotencyKeyStatus;
use App\Enums\TransferStatus;
use App\Enums\UserType;
use App\Exceptions\IdempotencyKeyFingerprintMismatchException;
use App\Exceptions\IdempotencyKeyInProgressException;
use App\Exceptions\TransientAuthorizerException;
use App\Jobs\SendTransferNotificationJob;
use App\Models\IdempotencyKey;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuthorizerClient;
use App\Services\IdempotencyKeyService;
use App\Services\WalletTransferService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

final class WalletTransferServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Mockery\MockInterface $authorizer;

    private Mockery\MockInterface $dispatcher;

    private IdempotencyKeyService $idempotencyService;

    private Mockery\MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizer = Mockery::mock(AuthorizerClient::class);
        $this->dispatcher = Mockery::mock(Dispatcher::class);
        $this->idempotencyService = new IdempotencyKeyService();
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
            ->andReturn(AuthorizerResult::Authorized);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(static fn (SendTransferNotificationJob $job): bool => $job->transferId > 0));

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $transfer = $service->execute($payer->id, $payee->id, 2500, 'key-1');

        $this->assertSame(TransferStatus::Completed, $transfer->status);
        $this->assertSame(7500, (int) $payer->fresh()?->wallet->getRawOriginal('balance'));
        $this->assertSame(2500, (int) $payee->fresh()?->wallet->getRawOriginal('balance'));

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'key-1',
            'status' => IdempotencyKeyStatus::Completed->value,
        ]);
    }

    public function test_it_fails_when_payer_is_merchant(): void
    {
        $merchant = User::factory()->create(['type' => UserType::Merchant->value]);
        $payee = User::factory()->create();

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
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
            $this->idempotencyService,
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
            $this->idempotencyService,
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
            ->andReturn(AuthorizerResult::Rejected);

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
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
            ->andReturn(AuthorizerResult::Authorized);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(static fn (SendTransferNotificationJob $job): bool => $job->transferId > 0));

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $first = $service->execute($payer->id, $payee->id, 1000, 'duplicate-key');
        $second = $service->execute($payer->id, $payee->id, 1000, 'duplicate-key');

        $this->assertSame($first->id, $second->id);
    }

    public function test_it_throws_when_same_key_used_with_different_payload(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        $this->authorizer->shouldReceive('authorize')
            ->once()
            ->andReturn(AuthorizerResult::Authorized);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(static fn (SendTransferNotificationJob $job): bool => $job->transferId > 0));

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $service->execute($payer->id, $payee->id, 1000, 'mismatch-key');

        $this->expectException(IdempotencyKeyFingerprintMismatchException::class);

        $service->execute($payer->id, $payee->id, 2000, 'mismatch-key');
    }

    public function test_it_throws_when_key_is_processing(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        IdempotencyKey::factory()->create([
            'key' => 'processing-key',
            'status' => IdempotencyKeyStatus::Processing,
            'fingerprint' => hash('sha256', implode(':', [$payer->id, $payee->id, 1000])),
        ]);

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $this->expectException(IdempotencyKeyInProgressException::class);

        $service->execute($payer->id, $payee->id, 1000, 'processing-key');
    }

    public function test_it_deletes_processing_key_on_transient_authorizer_exception(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        $this->authorizer->shouldReceive('authorize')
            ->once()
            ->andThrow(new TransientAuthorizerException());

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        try {
            $service->execute($payer->id, $payee->id, 1000, 'transient-key');
            $this->fail('Expected TransientAuthorizerException to be thrown.');
        } catch (TransientAuthorizerException) {
            $this->assertDatabaseMissing('idempotency_keys', [
                'key' => 'transient-key',
                'status' => IdempotencyKeyStatus::Processing->value,
            ]);
        }
    }

    public function test_it_records_failed_transfer_when_payer_not_found(): void
    {
        $payee = User::factory()->create();

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $transfer = $service->execute(99999, $payee->id, 1000, 'not-found-key');

        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertSame(FailureReason::PayerNotFound, $transfer->failure_reason);
    }

    public function test_it_replays_existing_failed_transfer_when_payer_not_found(): void
    {
        $payee = User::factory()->create();

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $first = $service->execute(99999, $payee->id, 1000, 'not-found-replay-key');
        $second = $service->execute(99999, $payee->id, 1000, 'not-found-replay-key');

        $this->assertSame(TransferStatus::Failed, $first->status);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(FailureReason::PayerNotFound, $second->failure_reason);
    }

    public function test_it_records_failed_transfer_when_amount_is_not_positive(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $transfer = $service->execute($payer->id, $payee->id, 0, 'invalid-amount-key');

        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertSame(FailureReason::InvalidAmount, $transfer->failure_reason);
    }

    public function test_it_recovers_stale_processing_idempotency_key(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        $this->authorizer->shouldReceive('authorize')
            ->once()
            ->andReturn(AuthorizerResult::Authorized);

        $this->dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(static fn (SendTransferNotificationJob $job): bool => $job->transferId > 0));

        IdempotencyKey::factory()->create([
            'key' => 'stale-key',
            'status' => IdempotencyKeyStatus::Processing,
            'fingerprint' => $this->idempotencyService->buildFingerprint($payer->id, $payee->id, 1000),
            'updated_at' => now()->subSeconds(600),
        ]);

        config(['transfer.idempotency_processing_ttl_seconds' => 300]);

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $transfer = $service->execute($payer->id, $payee->id, 1000, 'stale-key');

        $this->assertSame(TransferStatus::Completed, $transfer->status);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'stale-key',
            'status' => IdempotencyKeyStatus::Completed->value,
        ]);
    }

    public function test_it_replays_existing_transfer_from_completed_idempotency_key(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount' => 1000,
            'status' => TransferStatus::Completed,
        ]);

        IdempotencyKey::factory()->create([
            'key' => 'replay-key',
            'status' => IdempotencyKeyStatus::Completed,
            'fingerprint' => hash('sha256', implode(':', [$payer->id, $payee->id, 1000])),
            'transfer_id' => $transfer->id,
        ]);

        $service = new WalletTransferService(
            $this->authorizer,
            $this->dispatcher,
            $this->idempotencyService,
            $this->logger,
        );

        $replayed = $service->execute($payer->id, $payee->id, 1000, 'replay-key');

        $this->assertSame($transfer->getKey(), $replayed->getKey());
    }
}
