<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchasePrice = fake()->numberBetween(5_000, 500_000);

        return [
            'category_id' => Category::factory(),
            'code' => 'BRG-'.fake()->unique()->numerify('#####'),
            'name' => fake()->words(3, true),
            'brand' => fake()->optional()->company(),
            'unit' => fake()->randomElement(['pcs', 'botol', 'liter', 'set']),
            'purchase_price' => $purchasePrice,
            'sale_price' => $purchasePrice + fake()->numberBetween(1_000, 100_000),
            'min_stock' => fake()->numberBetween(0, 20),
            'shelf_location' => fake()->optional()->bothify('RAK-##'),
            'has_expiry' => fake()->boolean(30),
            'is_active' => true,
        ];
    }
}
