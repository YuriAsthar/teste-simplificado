<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => UserType::Common->value,
            'document_country' => 'BRA',
            'document_type' => DocumentType::BrCpf->value,
            'document_value' => fake()->unique()->numerify('###########'),
        ];
    }
}
