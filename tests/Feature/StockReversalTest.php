<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_reversing_stock_in_restores_batch_and_keeps_purchase_price(): void
    {
        $item = Item::factory()->create([
            'unit' => 'pcs',
            'purchase_price' => 10_000,
        ]);

        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 5,
                    'unit_cost' => 20_000,
                ],
            ],
        ]);

        $batch = StockBatch::query()->where('item_id', $item->id)->sole();

        $reversal = app(StockService::class)->reverse($document, 'Salah input');

        $this->assertSame('reversal', $reversal->type);
        $this->assertSame($document->id, $reversal->reverses_document_id);
        $this->assertSame('Salah input', $reversal->notes);
        $this->assertEqualsWithDelta(0, (float) $batch->refresh()->quantity_remaining, 0.001);
        $this->assertDatabaseHas('stock_movements', [
            'stock_document_id' => $reversal->id,
            'stock_batch_id' => $batch->id,
            'quantity' => -5,
            'unit_cost' => 20_000,
        ]);
        $this->assertSame('20000.00', $item->refresh()->purchase_price);
    }

    public function test_reversing_stock_out_adds_stock_back(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $document = app(StockService::class)->issue([
            'occurred_on' => '2026-11-10',
            'purpose' => 'workshop_use',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        app(StockService::class)->reverse($document, 'Batal pakai');

        $this->assertEqualsWithDelta(5, (float) $batch->refresh()->quantity_remaining, 0.001);
    }

    public function test_reversing_adjustment_restores_previous_quantity(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $document = app(StockService::class)->adjust([
            'occurred_on' => '2026-11-10',
            'notes' => 'Opname',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'counted_quantity' => 8,
                ],
            ],
        ]);

        app(StockService::class)->reverse($document, 'Salah hitung');

        $this->assertEqualsWithDelta(5, (float) $batch->refresh()->quantity_remaining, 0.001);
    }

    public function test_reversal_document_cannot_be_reversed(): void
    {
        $item = Item::factory()->create();
        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 1000],
            ],
        ]);

        $reversal = app(StockService::class)->reverse($document, 'Batal');

        $this->expectException(InvalidArgumentException::class);

        app(StockService::class)->reverse($reversal, 'Batal lagi');
    }

    public function test_document_cannot_be_reversed_twice(): void
    {
        $item = Item::factory()->create();
        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 1000],
            ],
        ]);

        app(StockService::class)->reverse($document, 'Batal');

        $this->expectException(InvalidArgumentException::class);

        app(StockService::class)->reverse($document, 'Batal kedua');
    }

    public function test_reversal_is_rejected_when_stock_is_insufficient(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 1000],
            ],
        ]);

        app(StockService::class)->issue([
            'occurred_on' => '2026-11-11',
            'purpose' => 'workshop_use',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 4],
            ],
        ]);

        try {
            app(StockService::class)->reverse($document, 'Batal masuk');
            $this->fail('Expected reversal to be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('stock_documents', 2);
            $this->assertDatabaseHas('stock_documents', [
                'id' => $document->id,
                'reverses_document_id' => null,
            ]);
            $this->assertSame(1.0, (float) StockBatch::query()->first()->quantity_remaining);
        }
    }

    public function test_reason_is_required_when_reversing_via_http(): void
    {
        $item = Item::factory()->create();
        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 1000],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.documents.reverse', $document),
            ['reason' => ''],
        );

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('stock_documents', 1);
    }

    public function test_reverse_action_creates_reversal_document(): void
    {
        $item = Item::factory()->create();
        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 1000],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.documents.reverse', $document),
            ['reason' => 'Salah input'],
        );

        $response->assertRedirect(route('stock.documents.show', $document));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'reversal',
            'reverses_document_id' => $document->id,
            'notes' => 'Salah input',
        ]);
    }

    public function test_document_detail_shows_reversal_action(): void
    {
        $item = Item::factory()->create();
        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 1000],
            ],
        ]);

        $this->withBasicAuthCredentials()
            ->get(route('stock.documents.show', $document))
            ->assertInertia(fn ($page) => $page
                ->component('Stock/Document')
                ->where('document.reversed_by', null));
    }

    public function test_reversing_po_receipt_reduces_received_quantity_and_status(): void
    {
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->ordered()->for($supplier)->create();
        $item = Item::factory()->create(['has_expiry' => false]);
        $poItem = $purchaseOrder->items()->create([
            'item_id' => $item->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_price' => 1000,
        ]);

        $stockDocument = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $purchaseOrder->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 1000],
            ],
        ]);

        $poItem->update(['quantity_received' => 5]);
        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_COMPLETED]);

        app(StockService::class)->reverse($stockDocument, 'Batal terima');

        $this->assertEqualsWithDelta(0, (float) $poItem->refresh()->quantity_received, 0.001);
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $purchaseOrder->refresh()->status);
        $this->assertSame(1.0, 1.0);
    }
}
