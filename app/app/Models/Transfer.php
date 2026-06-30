<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * @property TransferStatus $status
 * @property FailureReason|null $failure_reason
 */
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payer_wallet_id',
        'payee_wallet_id',
        'amount_cents',
        'currency',
        'idempotency_key',
        'status',
        'failure_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => MoneyCast::class,
            'currency' => CurrencyType::class,
            'status' => TransferStatus::class,
            'failure_reason' => FailureReason::class,
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function payerWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'payer_wallet_id');
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function payeeWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'payee_wallet_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeForWallet(Builder $query, int $walletId): Builder
    {
        return $query->where(static function (Builder $query) use ($walletId): void {
            $query->where('payer_wallet_id', $walletId)
                ->orWhere('payee_wallet_id', $walletId);
        });
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, TransferStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function transitionTo(TransferStatus $newStatus, ?FailureReason $reason = null): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition from '{$this->status->value}' to '{$newStatus->value}'."
            );
        }

        $this->status = $newStatus;

        if ($newStatus === TransferStatus::Failed && !is_null($reason)) {
            $this->failure_reason = $reason;
        }

        $this->save();
    }
}
