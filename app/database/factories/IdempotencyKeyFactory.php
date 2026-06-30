<?php

namespace Database\Factories;

use App\Models\IdempotencyKey;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cIdempotencyKey\u003e
 */
class IdempotencyKeyFactory extends Factory
{
    /**
     * @var class-string\u003cIdempotencyKey\u003e
     */
    protected $model = IdempotencyKey::class;

    /**
     * @return array\u003cstring, mixed\u003e
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->uuid(),
            'transfer_id' => Transfer::factory(),
        ];
    }
}
