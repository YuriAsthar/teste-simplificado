<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TransferResponseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transfer = $this->resource;

        return [
            'id' => $transfer->id,
            'status' => $transfer->status->value,
            'failure_reason' => $transfer->failure_reason?->value,
        ];
    }
}
