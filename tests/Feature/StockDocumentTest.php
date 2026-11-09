<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockDocument;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_documents_in_latest_order(): void
    {
        $itemA = Item::factory()->create(['has_expiry' => false]);
        $itemB = Item::factory()->create(['has_expiry' => false]);

        $inA = $this->receive($itemA, '2026-11-01', 10);
        $outA = $this->issue($itemA, '2026-11-10', 2);
        $inB = $this->receive($itemB, '2026-11-05', 5);

        $response = $this->withBasicAuthCredentials()->get(route('stock.documents.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Documents')
            ->has('documents.data', 3)
            ->where('documents.data.0.id', $outA->id)
            ->where('documents.data.1.id', $inB->id)
            ->where('documents.data.2.id', $inA->id)
            ->where('documents.data.0.stock_movements_count', 1));
    }

    public function test_index_filters_by_item(): void
    {
        $itemA = Item::factory()->create(['has_expiry' => false]);
        $itemB = Item::factory()->create(['has_expiry' => false]);

        $inA = $this->receive($itemA, '2026-11-01', 10);
        $outA = $this->issue($itemA, '2026-11-10', 2);
        $this->receive($itemB, '2026-11-05', 5);

        $response = $this->withBasicAuthCredentials()->get(
            route('stock.documents.index', ['item_id' => $itemA->id]),
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Documents')
            ->has('documents.data', 2)
            ->where('documents.data.0.id', $outA->id)
            ->where('documents.data.1.id', $inA->id));
    }

    public function test_index_filters_by_date_range(): void
    {
        $itemA = Item::factory()->create(['has_expiry' => false]);
        $itemB = Item::factory()->create(['has_expiry' => false]);

        $this->receive($itemA, '2026-11-01', 10);
        $outA = $this->issue($itemA, '2026-11-10', 2);
        $inB = $this->receive($itemB, '2026-11-05', 5);

        $response = $this->withBasicAuthCredentials()->get(
            route('stock.documents.index', [
                'date_from' => '2026-11-05',
                'date_to' => '2026-11-10',
            ]),
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Documents')
            ->has('documents.data', 2)
            ->where('documents.data.0.id', $outA->id)
            ->where('documents.data.1.id', $inB->id));
    }

    public function test_show_displays_outgoing_document_with_movements(): void
    {
        $item = Item::factory()->create([
            'has_expiry' => false,
            'unit' => 'pcs',
        ]);

        $this->receive($item, '2026-11-01', 10);
        $document = $this->issue($item, '2026-11-10', 2);

        $response = $this->withBasicAuthCredentials()->get(
            route('stock.documents.show', $document),
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Document')
            ->where('document.id', $document->id)
            ->where('document.type', 'out')
            ->where('document.purpose', 'service')
            ->has('document.stock_movements', 1)
            ->where('document.stock_movements.0.item.id', $item->id)
            ->where('document.stock_movements.0.quantity', '-2.00')
            ->where('document.stock_movements.0.stock_batch.expiry_date', null));
    }

    public function test_show_displays_incoming_document_with_unit_cost(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);

        $document = $this->receive($item, '2026-11-01', 10);

        $response = $this->withBasicAuthCredentials()->get(
            route('stock.documents.show', $document),
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Document')
            ->where('document.type', 'in')
            ->has('document.stock_movements', 1)
            ->where('document.stock_movements.0.unit_cost', '10000.00'));
    }

    private function receive(Item $item, string $date, float $quantity): StockDocument
    {
        return app(StockService::class)->receive([
            'occurred_on' => $date,
            'reference_number' => 'INV-'.$date,
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_cost' => 10_000,
                    'expiry_date' => null,
                ],
            ],
        ]);
    }

    private function issue(Item $item, string $date, float $quantity): StockDocument
    {
        return app(StockService::class)->issue([
            'occurred_on' => $date,
            'purpose' => 'service',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => $quantity],
            ],
        ]);
    }
}
