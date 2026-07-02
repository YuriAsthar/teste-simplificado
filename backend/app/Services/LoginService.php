<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\NewAccessToken;

final readonly class LoginService
{
    /**
     * @return array{user: User, access_token: NewAccessToken}|null
     */
    public function issueToken(string $email, string $password): ?array
    {
        if (!Auth::guard('web')->validate(['email' => $email, 'password' => $password])) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if (is_null($user)) {
            return null;
        }

        return [
            'user' => $user,
            'access_token' => $user->createToken('api-token'),
        ];
    }
}
