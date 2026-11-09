<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBatch>
 */
class StockBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'expiry_date' => fake()->boolean(70)
                ? fake()->dateTimeBetween('now', '+1 year')
                : null,
            'quantity_remaining' => fake()->randomFloat(2, 0, 100),
            'received_on' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function withoutExpiry(): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => null,
        ]);
    }

    public function expiringSoon(int $days = 15): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expiry_date' => now()->subDay()->toDateString(),
        ]);
    }
}
