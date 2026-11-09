<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockBatch;
use App\Models\StockDocument;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_document_id' => StockDocument::factory(),
            'item_id' => Item::factory(),
            'stock_batch_id' => StockBatch::factory(),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit_cost' => fake()->optional()->randomFloat(2, 1_000, 100_000),
            'unit_price' => null,
        ];
    }
}
