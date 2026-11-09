<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Services\StockService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReceiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_creates_batch_and_updates_purchase_price(): void
    {
        $item = Item::factory()->create([
            'has_expiry' => true,
            'purchase_price' => 1_000,
        ]);

        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'reference_number' => 'INV-001',
            'notes' => 'Penerimaan pertama',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'unit_cost' => 45_000,
                    'expiry_date' => '2027-01-01',
                ],
            ],
        ]);

        $this->assertSame('in', $document->type);
        $this->assertSame('INV-001', $document->reference_number);
        $this->assertDatabaseHas('stock_batches', [
            'item_id' => $item->id,
            'expiry_date' => '2027-01-01',
            'quantity_remaining' => 10,
            'received_on' => '2026-11-10',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_document_id' => $document->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_cost' => 45_000,
        ]);
        $this->assertEqualsWithDelta(45_000, (float) $item->refresh()->purchase_price, 0.001);
    }

    public function test_receive_adds_to_existing_batch_for_same_expiry_date(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);
        $service = app(StockService::class);

        $service->receive([
            'occurred_on' => '2026-11-01',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 4,
                    'unit_cost' => 10_000,
                    'expiry_date' => '2027-01-01',
                ],
            ],
        ]);

        $service->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 6,
                    'unit_cost' => 12_000,
                    'expiry_date' => '2027-01-01',
                ],
            ],
        ]);

        $this->assertDatabaseCount('stock_batches', 1);
        $this->assertDatabaseHas('stock_batches', [
            'item_id' => $item->id,
            'expiry_date' => '2027-01-01',
            'quantity_remaining' => 10,
        ]);
        $this->assertDatabaseHas('stock_movements', ['quantity' => 4, 'unit_cost' => 10_000]);
        $this->assertDatabaseHas('stock_movements', ['quantity' => 6, 'unit_cost' => 12_000]);
        $this->assertEqualsWithDelta(12_000, (float) $item->refresh()->purchase_price, 0.001);
    }

    public function test_receive_creates_separate_batches_for_different_expiry_dates(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);

        app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 3,
                    'unit_cost' => 10_000,
                    'expiry_date' => '2027-01-01',
                ],
                [
                    'item_id' => $item->id,
                    'quantity' => 7,
                    'unit_cost' => 10_000,
                    'expiry_date' => '2027-02-01',
                ],
            ],
        ]);

        $this->assertDatabaseCount('stock_batches', 2);
        $this->assertDatabaseHas('stock_batches', [
            'expiry_date' => '2027-01-01',
            'quantity_remaining' => 3,
        ]);
        $this->assertDatabaseHas('stock_batches', [
            'expiry_date' => '2027-02-01',
            'quantity_remaining' => 7,
        ]);
    }

    public function test_receive_creates_single_null_expiry_batch_for_item_without_expiry(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);

        app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 5,
                    'unit_cost' => 8_000,
                    'expiry_date' => null,
                ],
            ],
        ]);

        $this->assertDatabaseCount('stock_batches', 1);
        $this->assertDatabaseHas('stock_batches', [
            'item_id' => $item->id,
            'expiry_date' => null,
            'quantity_remaining' => 5,
        ]);
    }

    public function test_receive_is_rolled_back_when_a_line_is_invalid(): void
    {
        $item = Item::factory()->create([
            'has_expiry' => false,
            'purchase_price' => 1_000,
        ]);

        try {
            app(StockService::class)->receive([
                'occurred_on' => '2026-11-10',
                'lines' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => 5,
                        'unit_cost' => 8_000,
                        'expiry_date' => null,
                    ],
                    [
                        'item_id' => 999_999,
                        'quantity' => 1,
                        'unit_cost' => 1_000,
                        'expiry_date' => null,
                    ],
                ],
            ]);

            $this->fail('Expected ModelNotFoundException was not thrown.');
        } catch (ModelNotFoundException) {
            // Expected.
        }

        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseCount('stock_batches', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEqualsWithDelta(1_000, (float) $item->refresh()->purchase_price, 0.001);
    }
}
