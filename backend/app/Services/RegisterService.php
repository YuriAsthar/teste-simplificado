<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\ValueObjects\RegisterData;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

final readonly class RegisterService
{
    /**
     * @return array{user: User, access_token: NewAccessToken}
     */
    public function register(RegisterData $data): array
    {
        $document = $data->document;

        $user = User::query()->create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'type' => $data->type->value,
            'document_country' => $document->country,
            'document_type' => $document->type->value,
            'document_value' => $document->value,
        ]);

        $accessToken = $user->createToken('api-token');

        return [
            'user' => $user,
            'access_token' => $accessToken,
        ];
    }
}
