<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBatch;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_report_calculates_stock_and_inventory_value(): void
    {
        $item = Item::factory()->create([
            'name' => 'Oli Mesin',
            'purchase_price' => 10_000,
        ]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        StockBatch::factory()->for($item)->create([
            'expiry_date' => '2027-01-01',
            'quantity_remaining' => 3,
        ]);

        $other = Item::factory()->create(['name' => 'Busi', 'purchase_price' => 5_000]);
        StockBatch::factory()->for($other)->withoutExpiry()->create([
            'quantity_remaining' => 2,
        ]);

        $report = app(ReportService::class)->stockReport();

        $row = $report->firstWhere('id', $item->id);
        $this->assertSame('8.00', (string) $row->current_stock);
        $this->assertSame('80000.00', (string) $row->inventory_value);

        $total = $report->reduce(
            fn (string $carry, Item $item): string => bcadd($carry, (string) $item->inventory_value, 2),
            '0.00',
        );

        $this->assertSame('90000.00', $total);
    }

    public function test_stock_report_filters_by_search_category_and_active_status(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        $oli = Item::factory()->for($categoryA)->create([
            'name' => 'Oli Mesin',
            'code' => 'BRG-OLI',
            'is_active' => true,
        ]);
        $busi = Item::factory()->for($categoryB)->create([
            'name' => 'Busi',
            'code' => 'BRG-BUSI',
            'is_active' => false,
        ]);
        $filter = Item::factory()->for($categoryA)->create([
            'name' => 'Filter Oli',
            'code' => 'BRG-FLT',
            'is_active' => false,
        ]);

        $service = app(ReportService::class);

        $this->assertSame([$filter->id, $oli->id], $service->stockReport(['search' => 'Oli'])->pluck('id')->all());
        $this->assertSame(
            [$filter->id, $oli->id],
            $service->stockReport(['category_id' => $categoryA->id])->pluck('id')->all(),
        );
        $this->assertSame(
            [$busi->id, $filter->id],
            $service->stockReport(['is_active' => false])->pluck('id')->all(),
        );
        $this->assertSame(
            [$oli->id],
            $service->stockReport(['is_active' => true, 'search' => 'Oli'])->pluck('id')->all(),
        );
    }

    public function test_stock_card_has_running_balance_for_every_movement_type(): void
    {
        $item = Item::factory()->create(['has_expiry' => false, 'purchase_price' => 1_000]);
        $stock = app(StockService::class);

        $stock->receive([
            'occurred_on' => '2026-11-01',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 1_000],
            ],
        ]);

        $issue = $stock->issue([
            'occurred_on' => '2026-11-02',
            'purpose' => 'workshop_use',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $batch = StockBatch::query()->where('item_id', $item->id)->sole();

        $adjustment = $stock->adjust([
            'occurred_on' => '2026-11-03',
            'notes' => 'Stok opname',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'counted_quantity' => 4,
                ],
            ],
        ]);

        $this->travelTo(Carbon::parse('2026-11-04 10:00:00'));
        $stock->reverse($adjustment, 'Salah hitung');

        $card = app(ReportService::class)->stockCard($item);

        $this->assertCount(4, $card);
        $this->assertSame(
            ['5.00', '-2.00', '1.00', '-1.00'],
            $card->map(fn ($movement): string => (string) $movement->quantity)->all(),
        );
        $this->assertSame(
            ['5.00', '3.00', '4.00', '3.00'],
            $card->map(fn ($movement): string => (string) $movement->running_balance)->all(),
        );
        $this->assertSame('in', $card[0]->stockDocument->type);
        $this->assertTrue($issue->stockMovements->isNotEmpty());
    }

    public function test_stock_card_filters_by_date_and_document_type(): void
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

        $service = app(ReportService::class);

        $byDate = $service->stockCard($item, ['date_from' => '2026-11-02']);

        $this->assertCount(1, $byDate);
        $this->assertSame('-2.00', (string) $byDate[0]->quantity);
        $this->assertSame('3.00', (string) $byDate[0]->running_balance);

        $byType = $service->stockCard($item, ['type' => 'in']);

        $this->assertCount(1, $byType);
        $this->assertSame('5.00', (string) $byType[0]->quantity);
        $this->assertSame('5.00', (string) $byType[0]->running_balance);
    }

    public function test_stock_report_does_not_run_query_per_item(): void
    {
        $items = Item::factory()->count(5)->create();

        foreach ($items as $item) {
            StockBatch::factory()->for($item)->withoutExpiry()->create([
                'quantity_remaining' => 2,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ReportService::class)->stockReport();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(3, $queryCount);
    }
}
