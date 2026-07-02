<?php

namespace Database\Factories;

use App\Models\IdempotencyKey;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    /**
     * @var class-string<IdempotencyKey>
     */
    protected $model = IdempotencyKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->uuid(),
            'transfer_id' => Transfer::factory(),
        ];
    }
}
