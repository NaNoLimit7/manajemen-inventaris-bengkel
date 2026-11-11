<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'item_id' => Item::factory(),
            'quantity_ordered' => fake()->numberBetween(1, 20),
            'quantity_received' => 0,
            'unit_price' => fake()->numberBetween(1_000, 100_000),
        ];
    }
}
