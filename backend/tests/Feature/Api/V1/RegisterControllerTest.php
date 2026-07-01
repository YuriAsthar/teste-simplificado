<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RegisterControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_registers_a_new_user_with_valid_data(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'document_country' => 'BRA',
            'document_type' => 'br_cpf',
            'document_value' => '52998224725',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'type',
                    'document_country',
                    'document_type',
                    'document_value',
                    'created_at',
                    'updated_at',
                    'token',
                    'token_type',
                ],
            ])
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john.doe@example.com')
            ->assertJsonPath('data.type', 'common')
            ->assertJsonPath('data.document_country', 'BRA')
            ->assertJsonPath('data.document_type', 'br_cpf')
            ->assertJsonPath('data.document_value', '52998224725')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotNull($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
            'type' => 'common',
        ]);

        $user = User::query()->where('email', 'john.doe@example.com')->first();
        $this->assertTrue(Hash::check('secure-password', $user->password));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'api-token',
        ]);
    }

    public function test_it_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'existing@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'document_country' => 'BRA',
            'document_type' => 'br_cpf',
            'document_value' => '52998224725',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Invalid email');
    }

    public function test_it_requires_name_email_password_and_document_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'document_country',
                'document_type',
                'document_value',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'document_country' => 'BRA',
            'document_type' => 'br_cpf',
            'document_value' => '52998224725',
        ];
    }

    public function test_it_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'email' => 'not-an-email',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Invalid email');
    }

    public function test_it_rejects_password_without_confirmation(): void
    {
        $payload = $this->basePayload();
        unset($payload['password_confirmation']);

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_it_rejects_password_shorter_than_six_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'password' => '12345',
            'password_confirmation' => '12345',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_it_rejects_name_shorter_than_two_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'name' => 'J',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_registers_a_merchant_user_with_documents(): void
    {
        $payload = [
            'name' => 'Jane Shop',
            'email' => 'jane.shop@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'type' => 'merchant',
            'document_country' => 'BRA',
            'document_type' => 'br_cnpj',
            'document_value' => '11222333000181',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Jane Shop')
            ->assertJsonPath('data.email', 'jane.shop@example.com')
            ->assertJsonPath('data.type', 'merchant')
            ->assertJsonPath('data.document_country', 'BRA')
            ->assertJsonPath('data.document_type', 'br_cnpj')
            ->assertJsonPath('data.document_value', '11222333000181');

        $this->assertDatabaseHas('users', [
            'email' => 'jane.shop@example.com',
            'type' => 'merchant',
            'document_country' => 'BRA',
            'document_type' => 'br_cnpj',
            'document_value' => '11222333000181',
        ]);
    }

    public function test_it_rejects_invalid_user_type(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'type' => 'admin',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_it_rejects_document_with_invalid_country(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'document_country' => 'BRAZIL',
            'document_type' => 'br_cpf',
            'document_value' => '52998224725',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_country']);
    }

    public function test_it_rejects_document_type_not_allowed_for_country(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'document_country' => 'USA',
            'document_type' => 'br_cpf',
            'document_value' => '52998224725',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type']);
    }

    public function test_it_rejects_document_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'document_value' => '52998224725',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_country', 'document_type']);
    }

    public function test_it_rejects_document_with_invalid_type_enum(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'invalid_type',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type']);
    }

    public function test_it_accepts_formatted_cpf_and_stores_digits_only(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_value' => '529.982.247-25',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.document_value', '52998224725');

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'document_value' => '52998224725',
        ]);
    }

    public function test_it_rejects_invalid_cpf(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_value' => '12345678901',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_rejects_cpf_with_wrong_length(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_value' => '5299822472',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_rejects_cpf_with_repeated_digits(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_value' => '11111111111',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_rejects_cpf_with_wrong_check_digits(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_value' => '52998224799',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_accepts_valid_cnpj_and_stores_digits_only(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'br_cnpj',
            'document_value' => '11.222.333/0001-81',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.document_value', '11222333000181');

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'document_value' => '11222333000181',
        ]);
    }

    public function test_it_rejects_invalid_cnpj(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'br_cnpj',
            'document_value' => '11222333000195',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_rejects_cnpj_with_wrong_length(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'br_cnpj',
            'document_value' => '1122233300018',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_rejects_cnpj_with_repeated_digits(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'br_cnpj',
            'document_value' => '22222222222222',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_rejects_cnpj_with_wrong_check_digits(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'br_cnpj',
            'document_value' => '11222333000199',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_accepts_alphanumeric_cnpj_and_stores_uppercase(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'br_cnpj',
            'document_value' => '12.ABC.345/0001-88',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.document_value', '12ABC345000188');

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'document_value' => '12ABC345000188',
        ]);
    }

    public function test_it_rejects_invalid_alphanumeric_cnpj(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'br_cnpj',
            'document_value' => '12.ABC.345/0001-99',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_rejects_cnpj_with_invalid_length_after_normalization(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_type' => 'br_cnpj',
            'document_value' => '11.222.333/0001',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_rejects_duplicate_document_after_normalization(): void
    {
        User::factory()->create([
            'document_country' => 'BRA',
            'document_type' => 'br_cpf',
            'document_value' => '52998224725',
        ]);

        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'email' => 'different@example.com',
            'document_value' => '529.982.247-25',
        ]));

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'duplicate_record');
    }

    public function test_it_preserves_cpf_leading_zeros(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_value' => '012.345.678-90',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.document_value', '01234567890');

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'document_value' => '01234567890',
        ]);
    }

    public function test_it_rejects_cpf_with_invalid_length_after_normalization(): void
    {
        $response = $this->postJson('/api/v1/auth/register', array_merge($this->basePayload(), [
            'document_value' => '012.345.678-9',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_value']);
    }

    public function test_it_trims_other_document_type_value(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'US User',
            'email' => 'us.user@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'document_country' => 'USA',
            'document_type' => 'us_ein',
            'document_value' => ' 12-3456789 ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_value', '12-3456789');

        $this->assertDatabaseHas('users', [
            'email' => 'us.user@example.com',
            'document_value' => '12-3456789',
        ]);
    }
}
