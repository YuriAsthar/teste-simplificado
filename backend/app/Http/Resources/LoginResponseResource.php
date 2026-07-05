<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

final class LoginResponseResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly NewAccessToken $accessToken,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'token' => $this->accessToken->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
