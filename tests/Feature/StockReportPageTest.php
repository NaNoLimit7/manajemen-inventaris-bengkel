<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBatch;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_page_shows_summary_and_filtered_rows(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        $item = Item::factory()->for($category)->create([
            'name' => 'Oli Mesin',
            'code' => 'BRG-OLI',
            'purchase_price' => 10_000,
            'is_active' => true,
        ]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $inactive = Item::factory()->for($otherCategory)->create([
            'name' => 'Busi Lama',
            'purchase_price' => 5_000,
            'is_active' => false,
        ]);
        StockBatch::factory()->for($inactive)->withoutExpiry()->create([
            'quantity_remaining' => 2,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.stock', [
            'category_id' => $category->id,
            'is_active' => '1',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Stock')
            ->has('items', 1)
            ->where('items.0.id', $item->id)
            ->where('items.0.current_stock', '5.00')
            ->where('items.0.inventory_value', '50000.00')
            ->where('summary.item_count', 1)
            ->where('summary.total_inventory_value', '50000.00')
            ->where('filters.category_id', $category->id)
            ->where('filters.is_active', '1')
            ->has('categories', 2));
    }

    public function test_stock_page_shows_all_items_without_filters(): void
    {
        Item::factory()->count(3)->create();
        Item::factory()->create(['is_active' => false]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.stock'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Stock')
            ->has('items', 4)
            ->where('summary.item_count', 4));
    }

    public function test_stock_card_page_shows_item_summary_and_movements(): void
    {
        $category = Category::factory()->create();
        $item = Item::factory()->for($category)->create([
            'name' => 'Oli Mesin',
            'unit' => 'botol',
            'min_stock' => 2,
            'has_expiry' => false,
        ]);

        $stock = app(StockService::class);
        $stock->receive([
            'occurred_on' => '2026-11-01',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 1_000],
            ],
        ]);
        $stock->issue([
            'occurred_on' => '2026-11-02',
            'purpose' => 'workshop_use',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.stock-card', $item));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/StockCard')
            ->where('item.id', $item->id)
            ->where('item.name', 'Oli Mesin')
            ->where('item.category.id', $category->id)
            ->where('item.current_stock', '3.00')
            ->has('movements', 2)
            ->where('movements.0.quantity', '5.00')
            ->where('movements.0.running_balance', '5.00')
            ->where('movements.1.quantity', '-2.00')
            ->where('movements.1.running_balance', '3.00')
            ->where('filters.date_from', ''));
    }

    public function test_stock_card_page_filters_by_date_and_type(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);
        $stock = app(StockService::class);

        $stock->receive([
            'occurred_on' => '2026-11-01',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 1_000],
            ],
        ]);
        $stock->issue([
            'occurred_on' => '2026-11-02',
            'purpose' => 'workshop_use',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.stock-card', [
            'item' => $item,
            'date_from' => '2026-11-02',
            'type' => 'out',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('movements', 1)
            ->where('movements.0.quantity', '-2.00')
            ->where('movements.0.running_balance', '3.00')
            ->where('filters.date_from', '2026-11-02')
            ->where('filters.type', 'out'));
    }

    public function test_stock_card_page_shows_empty_state_data(): void
    {
        $item = Item::factory()->create();

        $response = $this->withBasicAuthCredentials()->get(route('reports.stock-card', $item));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/StockCard')
            ->has('movements', 0)
            ->where('item.current_stock', '0.00'));
    }
}
