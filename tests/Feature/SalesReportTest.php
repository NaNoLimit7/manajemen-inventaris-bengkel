<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\StockBatch;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_totals_series_items_and_customers(): void
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

        $report = app(ReportService::class)->salesReport([
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'group_by' => 'day',
        ]);

        $this->assertEqualsWithDelta(350_000, $report['total_sales'], 0.001);
        $this->assertSame(3, $report['transaction_count']);
        $this->assertSame('day', $report['group_by']);

        $this->assertSame('2026-11-03', $report['series'][0]['period']);
        $this->assertEqualsWithDelta(200_000, $report['series'][0]['total'], 0.001);
        $this->assertSame(2, $report['series'][0]['transaction_count']);
        $this->assertSame('2026-11-10', $report['series'][1]['period']);
        $this->assertEqualsWithDelta(150_000, $report['series'][1]['total'], 0.001);

        $this->assertSame('Oli Mesin', $report['items'][0]['item_name']);
        $this->assertEqualsWithDelta(5, $report['items'][0]['quantity'], 0.001);
        $this->assertEqualsWithDelta(250_000, $report['items'][0]['total'], 0.001);
        $this->assertSame('Busi', $report['items'][1]['item_name']);

        $this->assertSame('Oli Mesin', $report['best_sellers'][0]['item_name']);

        $this->assertSame('Budi', $report['customers'][0]['customer_name']);
        $this->assertEqualsWithDelta(250_000, $report['customers'][0]['total'], 0.001);
        $this->assertSame(2, $report['customers'][0]['transaction_count']);
    }

    public function test_sales_report_groups_by_month(): void
    {
        $item = Item::factory()->create();
        StockBatch::factory()->for($item)->withoutExpiry()->create(['quantity_remaining' => 20]);

        $stock = app(StockService::class);
        $stock->issue([
            'occurred_on' => '2026-11-03',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 10_000],
            ],
        ]);
        $stock->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 10_000],
            ],
        ]);

        $report = app(ReportService::class)->salesReport([
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'group_by' => 'month',
        ]);

        $this->assertCount(1, $report['series']);
        $this->assertSame('2026-11', $report['series'][0]['period']);
        $this->assertEqualsWithDelta(30_000, $report['series'][0]['total'], 0.001);
        $this->assertSame(2, $report['series'][0]['transaction_count']);
        $this->assertEqualsWithDelta(3, $report['items'][0]['quantity'], 0.001);
    }

    public function test_cancelled_sale_does_not_count_in_value_quantity_or_transactions(): void
    {
        $item = Item::factory()->create(['name' => 'Oli Mesin']);
        StockBatch::factory()->for($item)->withoutExpiry()->create(['quantity_remaining' => 20]);

        $stock = app(StockService::class);

        $cancelled = $stock->issue([
            'occurred_on' => '2026-11-04',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50_000],
            ],
        ]);
        $stock->reverse($cancelled, 'Batal jual');

        $stock->issue([
            'occurred_on' => '2026-11-05',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 30_000],
            ],
        ]);

        $report = app(ReportService::class)->salesReport([
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'group_by' => 'day',
        ]);

        $this->assertEqualsWithDelta(30_000, $report['total_sales'], 0.001);
        $this->assertSame(1, $report['transaction_count']);
        $this->assertCount(1, $report['items']);
        $this->assertEqualsWithDelta(1, $report['items'][0]['quantity'], 0.001);
        $this->assertEqualsWithDelta(30_000, $report['items'][0]['total'], 0.001);
    }

    public function test_sales_report_respects_date_filters(): void
    {
        $item = Item::factory()->create();
        StockBatch::factory()->for($item)->withoutExpiry()->create(['quantity_remaining' => 20]);

        $stock = app(StockService::class);
        $stock->issue([
            'occurred_on' => '2026-10-31',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 10_000],
            ],
        ]);
        $stock->issue([
            'occurred_on' => '2026-11-05',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 10_000],
            ],
        ]);

        $report = app(ReportService::class)->salesReport([
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
        ]);

        $this->assertEqualsWithDelta(10_000, $report['total_sales'], 0.001);
        $this->assertSame(1, $report['transaction_count']);
        $this->assertSame('2026-11-05', $report['series'][0]['period']);
    }

    public function test_sales_report_is_empty_without_sales(): void
    {
        Item::factory()->create();

        $report = app(ReportService::class)->salesReport([
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
        ]);

        $this->assertSame(0.0, $report['total_sales']);
        $this->assertSame(0, $report['transaction_count']);
        $this->assertSame([], $report['series']);
        $this->assertSame([], $report['items']);
        $this->assertSame([], $report['best_sellers']);
        $this->assertSame([], $report['customers']);
    }
}
