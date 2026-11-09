<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBatch;
use App\Models\StockDocument;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_item_with_manual_code(): void
    {
        $category = Category::factory()->create();

        $response = $this->withBasicAuthCredentials()->post(
            route('items.store'),
            $this->validPayload($category, ['code' => 'BRG-MANUAL']),
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'code' => 'BRG-MANUAL',
            'name' => 'Oli Mesin',
        ]);
    }

    public function test_store_generates_code_from_id_when_code_is_empty(): void
    {
        $category = Category::factory()->create();

        $response = $this->withBasicAuthCredentials()->post(
            route('items.store'),
            $this->validPayload($category, ['code' => '']),
        );

        $response->assertRedirect();

        $item = Item::query()->latest('id')->firstOrFail();

        $this->assertSame(sprintf('BRG-%05d', $item->id), $item->code);
    }

    public function test_validation_rejects_duplicate_code_and_invalid_category(): void
    {
        $category = Category::factory()->create();
        Item::factory()->create(['code' => 'BRG-DUP']);

        $response = $this->withBasicAuthCredentials()->post(
            route('items.store'),
            $this->validPayload($category, [
                'code' => 'BRG-DUP',
                'category_id' => 999999,
            ]),
        );

        $response->assertSessionHasErrors(['code', 'category_id']);
    }

    public function test_update_can_deactivate_item(): void
    {
        $item = Item::factory()->create(['is_active' => true]);

        $response = $this->withBasicAuthCredentials()->put(
            route('items.update', $item),
            $this->validPayload($item->category, ['is_active' => false]),
        );

        $response->assertRedirect(route('items.show', $item));
        $this->assertFalse($item->refresh()->is_active);
    }

    public function test_has_expiry_cannot_change_after_stock_history_exists(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);
        $this->createStockHistory($item);

        $response = $this->withBasicAuthCredentials()->put(
            route('items.update', $item),
            $this->validPayload($item->category, ['has_expiry' => true]),
        );

        $response->assertSessionHasErrors('has_expiry');
        $this->assertFalse($item->refresh()->has_expiry);
    }

    public function test_item_with_stock_history_cannot_be_deleted(): void
    {
        $item = Item::factory()->create();
        $this->createStockHistory($item);

        $response = $this->withBasicAuthCredentials()->delete(route('items.destroy', $item));

        $response->assertRedirect(route('items.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_item_without_history_can_be_deleted(): void
    {
        $item = Item::factory()->create();

        $response = $this->withBasicAuthCredentials()->delete(route('items.destroy', $item));

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_index_searches_and_filters_items(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        $itemA = Item::factory()->for($categoryA)->create([
            'name' => 'Oli Mesin',
            'code' => 'BRG-AAA',
        ]);

        Item::factory()->for($categoryB)->create([
            'name' => 'Busi',
            'code' => 'BRG-BBB',
        ]);

        $response = $this->withBasicAuthCredentials()->get(
            route('items.index', [
                'search' => 'Oli',
                'category_id' => $categoryA->id,
            ]),
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Items/Index')
            ->has('items.data', 1)
            ->where('items.data.0.code', 'BRG-AAA')
            ->where('filters.search', 'Oli')
            ->where('filters.category_id', $categoryA->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'code' => 'BRG-TEST',
            'name' => 'Oli Mesin',
            'category_id' => $category->id,
            'brand' => 'Yamaha',
            'unit' => 'botol',
            'purchase_price' => 45_000,
            'sale_price' => 55_000,
            'min_stock' => 6,
            'shelf_location' => 'RAK-01',
            'has_expiry' => false,
            'is_active' => true,
        ], $overrides);
    }

    private function createStockHistory(Item $item): void
    {
        $document = StockDocument::factory()->create(['type' => 'in']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create();

        StockMovement::factory()->create([
            'stock_document_id' => $document->id,
            'item_id' => $item->id,
            'stock_batch_id' => $batch->id,
            'quantity' => 1,
        ]);
    }
}
