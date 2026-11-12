<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => 'item.updated',
            'auditable_type' => Item::class,
            'auditable_id' => Item::factory(),
            'before' => ['name' => 'Oli Mesin'],
            'after' => ['name' => 'Oli Mesin Baru'],
        ];
    }
}
