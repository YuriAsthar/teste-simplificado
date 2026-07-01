<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OutboxStatus;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboxEvent>
 */
class OutboxEventFactory extends Factory
{
    /**
     * @var class-string<OutboxEvent>
     */
    protected $model = OutboxEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'aggregate_type' => 'transfer',
            'aggregate_id' => fake()->numberBetween(1, 100000),
            'event_type' => 'transfer.completed',
            'payload' => [],
            'status' => OutboxStatus::Pending->value,
            'attempts' => 0,
            'last_error_at' => null,
        ];
    }
}
