<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_stock_is_summed_from_batches(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);

        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 3.5,
        ]);

        StockBatch::factory()->for($item)->create([
            'expiry_date' => now()->addMonth()->toDateString(),
            'quantity_remaining' => 1.25,
        ]);

        $loadedItem = Item::query()->withCurrentStock()->findOrFail($item->id);

        $this->assertEqualsWithDelta(4.75, (float) $loadedItem->current_stock, 0.001);
        $this->assertCount(2, $loadedItem->batches);
        $this->assertTrue($loadedItem->category->is($item->category));
    }

    public function test_current_stock_falls_back_to_batch_sum(): void
    {
        $item = Item::factory()->create();

        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 2,
        ]);

        $this->assertEqualsWithDelta(2.0, (float) $item->current_stock, 0.001);
    }
}
