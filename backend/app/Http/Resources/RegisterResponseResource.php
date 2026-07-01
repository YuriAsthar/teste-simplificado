<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

final class RegisterResponseResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly NewAccessToken $accessToken,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'type' => $user->type->value,
            'document_country' => $user->document_country,
            'document_type' => $user->document_type->value,
            'document_value' => $user->document_value,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'token' => $this->accessToken->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
