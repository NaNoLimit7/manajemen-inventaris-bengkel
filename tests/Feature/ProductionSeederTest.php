<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeder_creates_required_categories_and_setting(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertDatabaseCount('categories', 3);
        $this->assertDatabaseHas('categories', ['slug' => 'consumable']);
        $this->assertDatabaseHas('categories', ['slug' => 'routine']);
        $this->assertDatabaseHas('categories', ['slug' => 'major']);
        $this->assertDatabaseHas('settings', [
            'key' => 'expiry_warning_days',
            'value' => '30',
        ]);
    }

    public function test_production_seeder_is_idempotent_and_preserves_changed_setting(): void
    {
        $this->seed(ProductionSeeder::class);

        Setting::query()
            ->where('key', 'expiry_warning_days')
            ->update(['value' => '45']);

        $this->seed(ProductionSeeder::class);

        $this->assertDatabaseCount('categories', 3);
        $this->assertDatabaseHas('settings', [
            'key' => 'expiry_warning_days',
            'value' => '45',
        ]);
    }
}
