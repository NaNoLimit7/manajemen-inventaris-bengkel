<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\StockBatch;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_page_shows_summary_series_items_and_customers(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi']);
        $oli = Item::factory()->create(['name' => 'Oli Mesin', 'unit' => 'botol']);
        $busi = Item::factory()->create(['name' => 'Busi', 'unit' => 'pcs']);

        StockBatch::factory()->for($oli)->withoutExpiry()->create(['quantity_remaining' => 20]);
        StockBatch::factory()->for($busi)->withoutExpiry()->create(['quantity_remaining' => 20]);

        $stock = app(StockService::class);
        $stock->issue([
            'occurred_on' => '2026-11-03',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $oli->id, 'quantity' => 2, 'unit_price' => 50_000],
            ],
        ]);
        $stock->issue([
            'occurred_on' => '2026-11-03',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $busi->id, 'quantity' => 1, 'unit_price' => 100_000],
            ],
        ]);
        $stock->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $oli->id, 'quantity' => 3, 'unit_price' => 50_000],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.sales', [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'group_by' => 'day',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Sales')
            ->where('report.total_sales', 350_000)
            ->where('report.transaction_count', 3)
            ->where('report.group_by', 'day')
            ->has('report.series', 2)
            ->where('report.series.0.period', '2026-11-03')
            ->where('report.series.0.total', 200_000)
            ->where('report.series.0.transaction_count', 2)
            ->has('report.items', 2)
            ->where('report.items.0.item_name', 'Oli Mesin')
            ->where('report.items.0.quantity', 5)
            ->has('report.best_sellers', 2)
            ->where('report.best_sellers.0.item_name', 'Oli Mesin')
            ->has('report.customers', 1)
            ->where('report.customers.0.customer_name', 'Budi')
            ->where('report.customers.0.total', 250_000)
            ->where('filters.group_by', 'day')
            ->where('filters.date_from', '2026-11-01')
            ->where('filters.date_to', '2026-11-30'));
    }

    public function test_sales_page_groups_by_month(): void
    {
        $item = Item::factory()->create();
        StockBatch::factory()->for($item)->withoutExpiry()->create(['quantity_remaining' => 20]);

        app(StockService::class)->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 10_000],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.sales', [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'group_by' => 'month',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('report.group_by', 'month')
            ->has('report.series', 1)
            ->where('report.series.0.period', '2026-11')
            ->where('report.series.0.total', 20_000));
    }

    public function test_sales_page_defaults_to_current_month(): void
    {
        $this->travelTo('2026-11-12 10:00:00');

        $response = $this->withBasicAuthCredentials()->get(route('reports.sales'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Sales')
            ->where('filters.date_from', '2026-11-01')
            ->where('filters.date_to', '2026-11-30')
            ->where('filters.group_by', 'day')
            ->where('report.total_sales', 0)
            ->has('report.items', 0)
            ->has('report.customers', 0));
    }
}
