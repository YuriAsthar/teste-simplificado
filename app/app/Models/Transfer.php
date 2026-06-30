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
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'payer_id',
        'payee_id',
        'amount',
        'currency',
        'idempotency_key',
        'status',
        'failure_reason',
        'notified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'currency' => CurrencyType::class,
            'status' => TransferStatus::class,
            'failure_reason' => FailureReason::class,
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_id');
    }

    /**
     * @return HasOne<IdempotencyKey, $this>
     */
    public function idempotencyKey(): HasOne
    {
        return $this->hasOne(IdempotencyKey::class);
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(static function (Builder $query) use ($userId): void {
            $query->where('payer_id', $userId)
                ->orWhere('payee_id', $userId);
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

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopePendingNotification(Builder $query): Builder
    {
        return $query->where('status', TransferStatus::Completed->value)
            ->whereNull('notified_at')
            ->whereDate('created_at', '>=', now()->subDays(30)->toDateString());
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
