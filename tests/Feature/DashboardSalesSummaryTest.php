<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockBatch;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardSalesSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sales_summary_totals_current_month(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 20,
        ]);

        $service = app(StockService::class);

        $service->issue([
            'occurred_on' => '2026-11-03',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50_000],
            ],
        ]);

        $service->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 3, 'unit_price' => 333.33],
            ],
        ]);

        $summary = app(ReportService::class)->salesSummary();

        $this->assertEqualsWithDelta(100_999.99, $summary['total_sales'], 0.001);
        $this->assertSame(2, $summary['transaction_count']);
    }

    public function test_sales_outside_current_month_are_ignored(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 20,
        ]);

        $service = app(StockService::class);

        $service->issue([
            'occurred_on' => '2026-10-31',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 100_000],
            ],
        ]);

        $summary = app(ReportService::class)->salesSummary();

        $this->assertSame(0.0, $summary['total_sales']);
        $this->assertSame(0, $summary['transaction_count']);
    }

    public function test_cancelled_sale_does_not_add_to_sales_value(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 20,
        ]);

        $service = app(StockService::class);

        $document = $service->issue([
            'occurred_on' => '2026-11-03',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50_000],
            ],
        ]);

        $service->reverse($document, 'Batal jual');

        $summary = app(ReportService::class)->salesSummary();

        $this->assertSame(0.0, $summary['total_sales']);
        $this->assertSame(0, $summary['transaction_count']);
    }

    public function test_dashboard_shows_sales_summary(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 20,
        ]);

        app(StockService::class)->issue([
            'occurred_on' => '2026-11-03',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 4, 'unit_price' => 25_000],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('salesSummary.total_sales', 100_000)
            ->where('salesSummary.transaction_count', 1));
    }
}
