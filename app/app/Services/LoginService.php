<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;

final readonly class LoginService
{
    public function issueToken(string $email, string $password): ?NewAccessToken
    {
        if (!Auth::guard('web')->validate(['email' => $email, 'password' => $password])) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        return $user?->createToken('api-token');
    }
}
