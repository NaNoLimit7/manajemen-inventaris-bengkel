<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\StockBatch;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_escapes_formula_like_values(): void
    {
        Item::factory()->create([
            'name' => '=SUM(1,1)',
            'code' => '@code',
        ]);

        $content = $this->withBasicAuthCredentials()
            ->get(route('reports.stock.csv'))
            ->streamedContent();

        $this->assertStringContainsString("'=SUM(1,1)", $content);
        $this->assertStringContainsString("'@code", $content);
    }

    public function test_stock_csv_contains_headers_rows_and_applies_filters(): void
    {
        $oli = Item::factory()->create([
            'name' => 'Oli Mesin',
            'code' => 'BRG-OLI',
            'purchase_price' => 10_000,
        ]);
        StockBatch::factory()->for($oli)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $busi = Item::factory()->create(['name' => 'Busi', 'code' => 'BRG-BUSI']);

        $response = $this->withBasicAuthCredentials()->get(route('reports.stock.csv', [
            'search' => 'Oli',
        ]));

        $response->assertOk();
        $response->assertDownload();

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Kode', $content);
        $this->assertStringContainsString('BRG-OLI', $content);
        $this->assertStringContainsString('Oli Mesin', $content);
        $this->assertStringContainsString('50000.00', $content);
        $this->assertStringNotContainsString($busi->code, $content);
    }

    public function test_stock_card_csv_can_be_downloaded_per_item_with_filters(): void
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

        $response = $this->withBasicAuthCredentials()->get(route('reports.stock-card.csv', [
            'item' => $item,
        ]));

        $response->assertOk();
        $response->assertDownload('kartu-stok-'.$item->code.'-'.now()->toDateString().'.csv');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Tanggal', $content);
        $this->assertStringContainsString('Masuk', $content);
        $this->assertStringContainsString('Keluar', $content);
        $this->assertStringContainsString('5.00', $content);
        $this->assertStringContainsString('3.00', $content);

        $filtered = $this->withBasicAuthCredentials()->get(route('reports.stock-card.csv', [
            'item' => $item,
            'date_from' => '2026-11-02',
            'type' => 'out',
        ]));

        $filteredContent = $filtered->streamedContent();

        $this->assertStringContainsString('2.00', $filteredContent);
        $this->assertStringNotContainsString('5.00', $filteredContent);
    }

    public function test_sales_csv_follows_period_and_grouping(): void
    {
        $customer = Customer::factory()->create(['name' => 'Budi']);
        $item = Item::factory()->create(['name' => 'Oli Mesin']);
        StockBatch::factory()->for($item)->withoutExpiry()->create(['quantity_remaining' => 20]);

        $stock = app(StockService::class);
        $stock->issue([
            'occurred_on' => '2026-11-03',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50_000],
            ],
        ]);
        $stock->issue([
            'occurred_on' => '2026-10-20',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 50_000],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.sales.csv', [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'group_by' => 'month',
        ]));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Laporan Penjualan', $content);
        $this->assertStringContainsString('Penjualan per Bulan', $content);
        $this->assertStringContainsString('2026-11', $content);
        $this->assertStringContainsString('100000', $content);
        $this->assertStringContainsString('Oli Mesin', $content);
        $this->assertStringContainsString('Budi', $content);
        $this->assertStringNotContainsString('2026-10-20', $content);
    }

    public function test_alerts_csv_contains_three_categories(): void
    {
        $lowItem = Item::factory()->create(['name' => 'Oli Menipis', 'min_stock' => 5, 'has_expiry' => false]);
        StockBatch::factory()->for($lowItem)->withoutExpiry()->create(['quantity_remaining' => 2]);

        $expiringItem = Item::factory()->create(['name' => 'Busi Hampir', 'has_expiry' => true, 'min_stock' => 0]);
        StockBatch::factory()->for($expiringItem)->create([
            'expiry_date' => now()->addDays(5)->toDateString(),
            'quantity_remaining' => 3,
        ]);

        $expiredItem = Item::factory()->create(['name' => 'Filter Basi', 'has_expiry' => true, 'min_stock' => 0]);
        StockBatch::factory()->for($expiredItem)->create([
            'expiry_date' => now()->subDays(5)->toDateString(),
            'quantity_remaining' => 4,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('reports.alerts.csv'));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Stok Menipis', $content);
        $this->assertStringContainsString('Batch Mendekati Kedaluwarsa', $content);
        $this->assertStringContainsString('Batch Sudah Kedaluwarsa', $content);
        $this->assertStringContainsString('Oli Menipis', $content);
        $this->assertStringContainsString('Busi Hampir', $content);
        $this->assertStringContainsString('Filter Basi', $content);
    }
}
