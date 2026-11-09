<?php

namespace Database\Factories;

use App\Models\StockDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockDocument>
 */
class StockDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['in', 'out', 'adjustment', 'reversal']);

        return [
            'type' => $type,
            'purpose' => $type === 'out'
                ? fake()->randomElement(['service', 'workshop_use', 'damaged_lost'])
                : null,
            'occurred_on' => now()->toDateString(),
            'reference_number' => $type === 'in' ? fake()->optional()->bothify('INV-####') : null,
            'supplier_id' => null,
            'customer_id' => null,
            'purchase_order_id' => null,
            'reverses_document_id' => null,
            'notes' => in_array($type, ['adjustment', 'reversal'], true) ? fake()->sentence() : null,
        ];
    }
}
