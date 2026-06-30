<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Casts\MoneyCast;
use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RelationalPaymentModelDodTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cannot_create_two_active_users_with_same_document(): void
    {
        User::factory()->create([
            'document_country' => 'BRA',
            'document_type' => 'cpf',
            'document_value' => '12345678901',
        ]);

        $this->expectException(QueryException::class);

        User::factory()->create([
            'document_country' => 'BRA',
            'document_type' => 'cpf',
            'document_value' => '12345678901',
        ]);
    }

    public function test_soft_deleted_user_can_be_recreated_with_same_document(): void
    {
        $user = User::factory()->create([
            'document_country' => 'BRA',
            'document_type' => 'cpf',
            'document_value' => '12345678901',
        ]);

        $user->delete();

        $recreated = User::factory()->create([
            'document_country' => 'BRA',
            'document_type' => 'cpf',
            'document_value' => '12345678901',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $recreated->id,
            'deleted_at' => null,
        ]);
    }

    public function test_creating_user_auto_creates_wallet_with_bra_currency_and_zero_balance(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => CurrencyType::BRA->value,
            'balance_cents' => 0,
        ]);
    }

    public function test_money_cast_converts_cents_to_decimal_and_back(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->assertSame('10.50', $cast->get($model, 'balance_cents', 1050, []));
        $this->assertSame(1050, $cast->set($model, 'balance_cents', '10.50', []));
        $this->assertSame(1050, $cast->set($model, 'balance_cents', 1050, []));
        $this->assertSame(205, $cast->set($model, 'balance_cents', '2.05', []));
        $this->assertNull($cast->set($model, 'balance_cents', null, []));
    }

    public function test_transfer_status_invalid_transition_throws_exception(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Completed,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $transfer->transitionTo(TransferStatus::Failed, FailureReason::Unknown);
    }

    /**
     * @return array<string, array<int, TransferStatus>>
     */
    public static function validStatusTransitionsProvider(): array
    {
        return [
            'pending to authorized' => [TransferStatus::Pending, TransferStatus::Authorized],
            'pending to failed' => [TransferStatus::Pending, TransferStatus::Failed],
            'pending to cancelled' => [TransferStatus::Pending, TransferStatus::Cancelled],
            'authorized to completed' => [TransferStatus::Authorized, TransferStatus::Completed],
            'authorized to failed' => [TransferStatus::Authorized, TransferStatus::Failed],
            'completed to refunded' => [TransferStatus::Completed, TransferStatus::Refunded],
        ];
    }

    #[DataProvider('validStatusTransitionsProvider')]
    public function test_valid_status_transitions_succeed(TransferStatus $from, TransferStatus $to): void
    {
        $transfer = Transfer::factory()->create([
            'status' => $from,
        ]);

        $transfer->transitionTo($to, FailureReason::Unknown);

        $this->assertSame($to->value, $transfer->fresh()?->status->value);
    }
}
