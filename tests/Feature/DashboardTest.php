<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_low_stock_items(): void
    {
        $item = Item::factory()->create(['min_stock' => 5]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 2,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('summary.low_stock', 1)
            ->has('lowStockItems', 1)
            ->where('lowStockItems.0.id', $item->id)
            ->where('lowStockItems.0.current_stock', 2));
    }

    public function test_dashboard_shows_expiring_and_expired_batches(): void
    {
        $item = Item::factory()->create(['has_expiry' => true, 'min_stock' => 0]);
        $expiring = StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->addDays(5)->toDateString(),
            'quantity_remaining' => 3,
        ]);
        $expired = StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->subDays(5)->toDateString(),
            'quantity_remaining' => 4,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('summary.expiring', 1)
            ->where('summary.expired', 1)
            ->has('expiringBatches', 1)
            ->where('expiringBatches.0.id', $expiring->id)
            ->has('expiredBatches', 1)
            ->where('expiredBatches.0.id', $expired->id));
    }

    public function test_dashboard_summary_matches_global_badges(): void
    {
        $lowItem = Item::factory()->create(['min_stock' => 4]);
        StockBatch::factory()->for($lowItem)->withoutExpiry()->create([
            'quantity_remaining' => 1,
        ]);

        $expiryItem = Item::factory()->create(['has_expiry' => true, 'min_stock' => 0]);
        StockBatch::factory()->for($expiryItem)->create([
            'expiry_date' => now()->addDays(5)->toDateString(),
            'quantity_remaining' => 1,
        ]);
        StockBatch::factory()->for($expiryItem)->create([
            'expiry_date' => now()->subDays(5)->toDateString(),
            'quantity_remaining' => 1,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('summary.low_stock', 1)
            ->where('summary.expiring', 1)
            ->where('summary.expired', 1)
            ->where('summary.total', 3)
            ->where('badges.low_stock', 1)
            ->where('badges.expiring', 1)
            ->where('badges.expired', 1)
            ->where('badges.total', 3));
    }

    public function test_dashboard_limits_alert_rows(): void
    {
        $item = Item::factory()->create(['min_stock' => 100]);

        for ($index = 0; $index < 15; $index++) {
            StockBatch::factory()->for($item)->create([
                'expiry_date' => now()->addDays($index + 1)->toDateString(),
                'quantity_remaining' => 1,
            ]);
        }

        $response = $this->withBasicAuthCredentials()->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('summary.expiring', 15)
            ->has('expiringBatches', 10));
    }
}
