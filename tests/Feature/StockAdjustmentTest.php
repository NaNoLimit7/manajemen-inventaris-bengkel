<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockBatch;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_lists_active_items_with_batches(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);
        StockBatch::factory()->for($item)->create([
            'expiry_date' => '2026-12-31',
            'quantity_remaining' => 5,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('stock.adjustments.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Adjustment')
            ->has('items', 1)
            ->where('items.0.id', $item->id)
            ->has('items.0.batches', 1)
            ->where('items.0.batches.0.quantity_remaining', 5));
    }

    public function test_positive_adjustment_creates_positive_movement(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $response = $this->withBasicAuthCredentials()->post(route('stock.adjustments.store'), [
            'occurred_on' => '2026-11-12',
            'notes' => 'Stok opname',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'counted_quantity' => 8,
                ],
            ],
        ]);

        $response->assertRedirect(route('stock.adjustments.create'));
        $response->assertSessionHas('success');
        $this->assertEqualsWithDelta(8, (float) $batch->refresh()->quantity_remaining, 0.001);
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'adjustment',
            'notes' => 'Stok opname',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_batch_id' => $batch->id,
            'quantity' => 3,
        ]);
    }

    public function test_negative_adjustment_creates_negative_movement(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        app(StockService::class)->adjust([
            'occurred_on' => '2026-11-12',
            'notes' => 'Barang rusak',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'counted_quantity' => 2,
                ],
            ],
        ]);

        $this->assertEqualsWithDelta(2, (float) $batch->refresh()->quantity_remaining, 0.001);
        $this->assertDatabaseHas('stock_movements', [
            'stock_batch_id' => $batch->id,
            'quantity' => -3,
        ]);
    }

    public function test_lines_with_zero_difference_do_not_create_movements(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $unchanged = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        app(StockService::class)->adjust([
            'occurred_on' => '2026-11-12',
            'notes' => 'Hitung ulang',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $unchanged->id,
                    'counted_quantity' => 5,
                ],
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $unchanged->id,
                    'counted_quantity' => 6,
                ],
            ],
        ]);

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertEqualsWithDelta(6, (float) $unchanged->refresh()->quantity_remaining, 0.001);
    }

    public function test_all_zero_differences_are_rejected(): void
    {
        $item = Item::factory()->create();
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(StockService::class)->adjust([
            'occurred_on' => '2026-11-12',
            'notes' => 'Tanpa selisih',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'counted_quantity' => 5,
                ],
            ],
        ]);
    }

    public function test_notes_are_required(): void
    {
        $item = Item::factory()->create();
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create();

        $response = $this->withBasicAuthCredentials()->post(route('stock.adjustments.store'), [
            'occurred_on' => '2026-11-12',
            'notes' => '',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'counted_quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('notes');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_batch_is_required_for_expiry_items(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);

        $response = $this->withBasicAuthCredentials()->post(route('stock.adjustments.store'), [
            'occurred_on' => '2026-11-12',
            'notes' => 'Opname',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'counted_quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.stock_batch_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_batch_must_belong_to_selected_item(): void
    {
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();
        $otherBatch = StockBatch::factory()->for($otherItem)->withoutExpiry()->create();

        $response = $this->withBasicAuthCredentials()->post(route('stock.adjustments.store'), [
            'occurred_on' => '2026-11-12',
            'notes' => 'Opname',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $otherBatch->id,
                    'counted_quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.stock_batch_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_negative_counted_quantity_is_rejected(): void
    {
        $item = Item::factory()->create();
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create();

        $response = $this->withBasicAuthCredentials()->post(route('stock.adjustments.store'), [
            'occurred_on' => '2026-11-12',
            'notes' => 'Opname',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'counted_quantity' => -1,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.counted_quantity');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_invalid_second_line_rolls_back_entire_transaction(): void
    {
        $item = Item::factory()->create();
        $firstBatch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        $otherItem = Item::factory()->create();
        $otherBatch = StockBatch::factory()->for($otherItem)->withoutExpiry()->create([
            'quantity_remaining' => 3,
        ]);

        try {
            app(StockService::class)->adjust([
                'occurred_on' => '2026-11-12',
                'notes' => 'Opname',
                'lines' => [
                    [
                        'item_id' => $item->id,
                        'stock_batch_id' => $firstBatch->id,
                        'counted_quantity' => 7,
                    ],
                    [
                        'item_id' => $item->id,
                        'stock_batch_id' => $otherBatch->id,
                        'counted_quantity' => 1,
                    ],
                ],
            ]);

            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('stock_documents', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertEqualsWithDelta(5, (float) $firstBatch->refresh()->quantity_remaining, 0.001);
        }
    }
}
