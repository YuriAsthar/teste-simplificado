<?php

declare(strict_types=1);

namespace App\Models;

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
        'transfer_id',
    ];

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }
}
