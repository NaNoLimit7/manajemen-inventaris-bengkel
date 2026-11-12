<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\AlertService;
use App\Services\PurchaseOrderService;
use App\Services\ReportService;
use App\Services\StockService;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class M3IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_realistic_inventory_flow_produces_correct_reports_exports_and_audit(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->importItems();

        $oli = Item::query()->where('code', 'BRG-OLI')->firstOrFail();
        $busi = Item::query()->where('code', 'BRG-BUSI')->firstOrFail();

        $customer = Customer::factory()->create(['name' => 'Budi']);
        $today = now()->toDateString();
        $monthFrom = now()->startOfMonth()->toDateString();
        $monthTo = now()->endOfMonth()->toDateString();

        $stock = app(StockService::class);

        $stock->issue([
            'occurred_on' => $today,
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $oli->id, 'quantity' => 2, 'unit_price' => 55_000],
            ],
        ]);

        $cancelled = $stock->issue([
            'occurred_on' => $today,
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $busi->id, 'quantity' => 1, 'unit_price' => 30_000],
            ],
        ]);
        $stock->reverse($cancelled, 'Batal jual');

        $reportService = app(ReportService::class);

        $stockRows = $reportService->stockReport();
        $totalValue = $stockRows->reduce(
            fn (string $carry, Item $item): string => bcadd($carry, (string) $item->inventory_value, 2),
            '0.00',
        );

        $this->assertSame('360000.00', (string) $stockRows->firstWhere('id', $oli->id)->inventory_value);
        $this->assertSame('60000.00', (string) $stockRows->firstWhere('id', $busi->id)->inventory_value);
        $this->assertSame('620000.00', $totalValue);

        $card = $reportService->stockCard($oli);
        $this->assertCount(2, $card);
        $this->assertSame(
            ['10.00', '8.00'],
            $card->map(fn ($movement): string => (string) $movement->running_balance)->all(),
        );

        $sales = $reportService->salesReport([
            'date_from' => $monthFrom,
            'date_to' => $monthTo,
            'group_by' => 'day',
        ]);

        $this->assertEqualsWithDelta(110_000, $sales['total_sales'], 0.001);
        $this->assertSame(1, $sales['transaction_count']);
        $this->assertCount(1, $sales['items']);
        $this->assertEqualsWithDelta(2, $sales['items'][0]['quantity'], 0.001);

        $counts = app(AlertService::class)->counts();
        $this->assertSame(1, $counts['low_stock']);
        $this->assertSame(0, $counts['expiring']);
        $this->assertSame(0, $counts['expired']);

        $this->assertSame(3, AuditLog::query()->where('event', 'item.created')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'stock.reversed')->count());
        $this->assertSame(0, AuditLog::query()->where('event', 'stock.adjusted')->count());

        $stockCsv = $this->withBasicAuthCredentials()->get(route('reports.stock.csv'))->streamedContent();
        $this->assertStringContainsString('BRG-OLI', $stockCsv);
        $this->assertStringContainsString('360000.00', $stockCsv);

        $cardCsv = $this->withBasicAuthCredentials()->get(route('reports.stock-card.csv', $oli))->streamedContent();
        $this->assertStringContainsString('8.00', $cardCsv);

        $salesCsv = $this->withBasicAuthCredentials()->get(route('reports.sales.csv', [
            'date_from' => $monthFrom,
            'date_to' => $monthTo,
            'group_by' => 'day',
        ]))->streamedContent();
        $this->assertStringContainsString('110000', $salesCsv);
        $this->assertStringContainsString('Oli Mesin', $salesCsv);

        $alertsCsv = $this->withBasicAuthCredentials()->get(route('reports.alerts.csv'))->streamedContent();
        $this->assertStringContainsString('Busi', $alertsCsv);

        $supplier = Supplier::factory()->create(['name' => 'Toko Oli']);
        $purchaseOrder = app(PurchaseOrderService::class)->create([
            'supplier_id' => $supplier->id,
            'notes' => 'Restok oli',
            'lines' => [
                ['item_id' => $oli->id, 'quantity_ordered' => 5, 'unit_price' => 45_000],
            ],
        ]);

        $text = app(PurchaseOrderService::class)->formatText($purchaseOrder);

        $this->assertStringContainsString('Oli Mesin', $text);
        $this->assertStringContainsString('Total: Rp225.000,00', $text);
        $this->assertSame('draft', $purchaseOrder->status);
        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
    }

    private function importItems(): void
    {
        $content = implode("\n", [
            implode(',', ['code', 'name', 'category', 'brand', 'unit', 'purchase_price', 'sale_price', 'min_stock', 'shelf_location', 'has_expiry', 'initial_stock', 'expiry_date']),
            implode(',', ['BRG-OLI', 'Oli Mesin', 'consumable', 'Yamaha', 'botol', '45000', '55000', '4', 'RAK-01', 'tidak', '10', '']),
            implode(',', ['BRG-BUSI', 'Busi', 'routine', 'NGK', 'pcs', '20000', '30000', '10', 'RAK-02', 'tidak', '3', '']),
            implode(',', ['BRG-FILTER', 'Filter Udara', 'major', 'Yamaha', 'pcs', '40000', '60000', '2', 'RAK-03', 'ya', '5', '2027-01-01']),
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.import.store'),
            ['file' => UploadedFile::fake()->createWithContent('barang.csv', $content)],
        );

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseCount('items', 3);
    }
}
