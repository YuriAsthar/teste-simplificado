<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class LogoutControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Token revoked successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_revoked_token_cannot_access_protected_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/transfer');

        $response->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }
}
