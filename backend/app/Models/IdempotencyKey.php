<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdempotencyKeyStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdempotencyKey extends Model
{
    /** @use HasFactory<\Database\Factories\IdempotencyKeyFactory> */
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'key',
        'status',
        'fingerprint',
        'request_hash',
        'endpoint',
        'response_status',
        'response_body',
        'transfer_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IdempotencyKeyStatus::class,
            'response_body' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function fingerprint(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?string => $value ?? $this->request_hash,
        );
    }
}
