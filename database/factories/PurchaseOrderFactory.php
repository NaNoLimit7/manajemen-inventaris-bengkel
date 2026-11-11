<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PO-'.now()->format('Ym').'-'.fake()->unique()->numerify('####'),
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'ordered_on' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function ordered(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_on' => now()->toDateString(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrder::STATUS_COMPLETED,
            'ordered_on' => now()->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrder::STATUS_CANCELLED,
        ]);
    }
}
