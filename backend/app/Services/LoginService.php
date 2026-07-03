<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        $accessToken = $user->createToken('api-token');

        Log::info('Token issued.', [
            'user_id' => $user->id,
            'token_name' => 'api-token',
        ]);

        return [
            'user' => $user,
            'access_token' => $accessToken,
        ];
    }
}
