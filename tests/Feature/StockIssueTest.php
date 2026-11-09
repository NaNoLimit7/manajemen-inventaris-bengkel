<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Item;
use App\Models\StockBatch;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_uses_fefo_and_no_expiry_batch_last(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);

        $lateExpiry = StockBatch::factory()->for($item)->create([
            'expiry_date' => '2027-01-01',
            'quantity_remaining' => 3,
            'received_on' => '2026-11-01',
        ]);
        $earlyExpiry = StockBatch::factory()->for($item)->create([
            'expiry_date' => '2026-12-01',
            'quantity_remaining' => 2,
            'received_on' => '2026-11-01',
        ]);
        $noExpiry = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 10,
            'received_on' => '2026-10-01',
        ]);

        $document = app(StockService::class)->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'workshop_use',
            'notes' => 'Pemakaian bengkel',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 6],
            ],
        ]);

        $this->assertSame('out', $document->type);
        $this->assertSame('workshop_use', $document->purpose);
        $this->assertEqualsWithDelta(0, (float) $earlyExpiry->refresh()->quantity_remaining, 0.001);
        $this->assertEqualsWithDelta(0, (float) $lateExpiry->refresh()->quantity_remaining, 0.001);
        $this->assertEqualsWithDelta(9, (float) $noExpiry->refresh()->quantity_remaining, 0.001);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseHas('stock_movements', [
            'stock_batch_id' => $earlyExpiry->id,
            'quantity' => -2,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_batch_id' => $lateExpiry->id,
            'quantity' => -3,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_batch_id' => $noExpiry->id,
            'quantity' => -1,
        ]);
    }

    public function test_issue_rejects_insufficient_stock_and_rolls_back(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 2,
        ]);

        try {
            app(StockService::class)->issue([
                'occurred_on' => '2026-11-10',
                'purpose' => 'workshop_use',
                'lines' => [
                    ['item_id' => $item->id, 'quantity' => 5],
                ],
            ]);

            $this->fail('Expected InsufficientStockException was not thrown.');
        } catch (InsufficientStockException $exception) {
            $this->assertStringContainsString('Tersisa 2.00 pcs.', $exception->getMessage());
        }

        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEqualsWithDelta(2, (float) $batch->refresh()->quantity_remaining, 0.001);
    }

    public function test_issue_can_consume_exact_stock(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $document = app(StockService::class)->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'service',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5],
            ],
        ]);

        $this->assertEqualsWithDelta(0, (float) $batch->refresh()->quantity_remaining, 0.001);
        $this->assertDatabaseCount('stock_documents', 1);
        $this->assertDatabaseHas('stock_movements', [
            'stock_document_id' => $document->id,
            'stock_batch_id' => $batch->id,
            'quantity' => -5,
        ]);
    }

    public function test_issue_handles_decimal_quantities(): void
    {
        $item = Item::factory()->create(['unit' => 'liter']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 1.5,
        ]);

        app(StockService::class)->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'workshop_use',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1.25],
            ],
        ]);

        $this->assertEqualsWithDelta(0.25, (float) $batch->refresh()->quantity_remaining, 0.001);
        $this->assertDatabaseHas('stock_movements', [
            'stock_batch_id' => $batch->id,
            'quantity' => -1.25,
        ]);
    }

    public function test_sold_issue_stores_unit_price(): void
    {
        $item = Item::factory()->create([
            'unit' => 'pcs',
            'sale_price' => 55_000,
        ]);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 3,
        ]);

        $document = app(StockService::class)->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50_000],
            ],
        ]);

        $this->assertSame('sold', $document->purpose);
        $this->assertDatabaseHas('stock_movements', [
            'stock_document_id' => $document->id,
            'stock_batch_id' => $batch->id,
            'quantity' => -2,
            'unit_price' => 50_000,
        ]);
        $this->assertEqualsWithDelta(1, (float) $batch->refresh()->quantity_remaining, 0.001);
    }

    public function test_sold_issue_uses_sale_price_when_unit_price_is_omitted(): void
    {
        $item = Item::factory()->create([
            'unit' => 'pcs',
            'sale_price' => 55_000,
        ]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 3,
        ]);

        $document = app(StockService::class)->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'sold',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1],
            ],
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'stock_document_id' => $document->id,
            'unit_price' => 55_000,
        ]);
    }
}
