<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;

final readonly class LogoutService
{
    public function revokeCurrentToken(Request $request): void
    {
        $request->user()?->currentAccessToken()?->delete();
    }
}
