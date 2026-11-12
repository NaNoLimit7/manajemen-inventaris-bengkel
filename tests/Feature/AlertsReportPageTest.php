<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlertsReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_page_shows_all_three_categories(): void
    {
        $lowItem = Item::factory()->create([
            'name' => 'Oli Mesin',
            'code' => 'BRG-OLI',
            'min_stock' => 5,
            'has_expiry' => false,
        ]);
        StockBatch::factory()->for($lowItem)->withoutExpiry()->create([
            'quantity_remaining' => 2,
        ]);

        $expiringItem = Item::factory()->create([
            'name' => 'Busi',
            'has_expiry' => true,
            'min_stock' => 0,
        ]);
        $expiring = StockBatch::factory()->for($expiringItem)->create([
            'expiry_date' => now()->addDays(7)->toDateString(),
            'quantity_remaining' => 4,
        ]);

        $expiredItem = Item::factory()->create([
            'name' => 'Filter Lama',
            'has_expiry' => true,
            'min_stock' => 0,
        ]);
        $expired = StockBatch::factory()->for($expiredItem)->create([
            'expiry_date' => now()->subDays(3)->toDateString(),
            'quantity_remaining' => 6,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.alerts'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Alerts')
            ->where('counts.low_stock', 1)
            ->where('counts.expiring', 1)
            ->where('counts.expired', 1)
            ->where('counts.total', 3)
            ->has('lowStockItems', 1)
            ->where('lowStockItems.0.id', $lowItem->id)
            ->where('lowStockItems.0.current_stock', '2.00')
            ->where('lowStockItems.0.min_stock', '5.00')
            ->has('expiringBatches', 1)
            ->where('expiringBatches.0.id', $expiring->id)
            ->where('expiringBatches.0.item_name', 'Busi')
            ->where('expiringBatches.0.quantity_remaining', '4.00')
            ->has('expiredBatches', 1)
            ->where('expiredBatches.0.id', $expired->id)
            ->where('expiredBatches.0.item_name', 'Filter Lama'));
    }

    public function test_alerts_page_shows_empty_state_data(): void
    {
        $response = $this->withBasicAuthCredentials()->get(route('reports.alerts'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Alerts')
            ->where('counts.low_stock', 0)
            ->where('counts.expiring', 0)
            ->where('counts.expired', 0)
            ->where('counts.total', 0)
            ->has('lowStockItems', 0)
            ->has('expiringBatches', 0)
            ->has('expiredBatches', 0));
    }
}
