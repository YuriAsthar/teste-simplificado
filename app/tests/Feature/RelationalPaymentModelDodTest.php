<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Casts\MoneyCast;
use App\Enums\CurrencyType;
use App\Enums\DocumentType;
use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'document_type' => DocumentType::BrCpf->value,
            'document_value' => '12345678901',
        ]);

        $this->expectException(QueryException::class);

        User::factory()->create([
            'document_country' => 'BRA',
            'document_type' => DocumentType::BrCpf->value,
            'document_value' => '12345678901',
        ]);
    }

    public function test_soft_deleted_user_can_be_recreated_with_same_document(): void
    {
        $user = User::factory()->create([
            'document_country' => 'BRA',
            'document_type' => DocumentType::BrCpf->value,
            'document_value' => '12345678901',
        ]);

        $user->delete();

        $recreated = User::factory()->create([
            'document_country' => 'BRA',
            'document_type' => DocumentType::BrCpf->value,
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
            'balance' => 0,
        ]);
    }

    public function test_wallets_balance_column_defaults_to_zero_and_is_bigint(): void
    {
        $user = User::factory()->create();

        $column = $this->findColumn('wallets', 'balance');

        $this->assertNotNull($column);
        $this->assertSame('bigint', $column['type'] ?? null);
        $this->assertSame(0, $user->wallet->balance);
    }

    public function test_transfers_amount_column_is_required_bigint(): void
    {
        $column = $this->findColumn('transfers', 'amount');

        $this->assertNotNull($column);
        $this->assertSame('bigint', $column['type'] ?? null);
        $this->assertFalse($column['nullable'] ?? true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findColumn(string $table, string $name): ?array
    {
        foreach (Schema::getColumns($table) as $column) {
            if (($column['name'] ?? '') === $name) {
                return $column;
            }
        }

        return null;
    }

    public function test_creating_transfer_without_amount_fails(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('transfers')->insert([
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'currency' => CurrencyType::BRA->value,
            'idempotency_key' => 'missing-amount',
            'status' => TransferStatus::Completed->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_money_cast_returns_int_from_database_value(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->assertSame(1050, $cast->get($model, 'balance', '1050', []));
        $this->assertSame(1050, $cast->get($model, 'balance', 1050, []));
    }

    public function test_money_cast_set_accepts_int_only(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->assertSame(1050, $cast->set($model, 'balance', 1050, []));
    }

    public function test_money_cast_set_rejects_non_int_values(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line argument.type */
        $cast->set($model, 'balance', '10.50', []);
    }

    public function test_money_cast_get_rejects_null(): void
    {
        $cast = new MoneyCast();
        $model = new User();

        $this->expectException(InvalidArgumentException::class);

        $cast->get($model, 'balance', null, []);
    }

    public function test_transfer_status_invalid_transition_throws_exception(): void
    {
        /** @var Transfer $transfer */
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
        /** @var Transfer $transfer */
        $transfer = Transfer::factory()->create([
            'status' => $from,
        ]);

        $transfer->transitionTo($to, FailureReason::Unknown);

        $this->assertSame($to->value, $transfer->fresh()?->status->value);
    }
}
