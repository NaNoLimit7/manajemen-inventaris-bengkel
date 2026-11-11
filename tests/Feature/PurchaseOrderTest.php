<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_purchase_orders_with_filters(): void
    {
        $supplier = Supplier::factory()->create();
        $otherSupplier = Supplier::factory()->create();

        $draft = PurchaseOrder::factory()->for($supplier)->create(['number' => 'PO-202611-0001']);
        PurchaseOrder::factory()->for($otherSupplier)->ordered()->create();

        $response = $this->withBasicAuthCredentials()->get(route('purchase-orders.index', [
            'status' => 'draft',
            'supplier_id' => $supplier->id,
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('PurchaseOrders/Index')
            ->has('purchaseOrders.data', 1)
            ->where('purchaseOrders.data.0.id', $draft->id)
            ->where('filters.status', 'draft'));
    }

    public function test_index_paginates_purchase_orders(): void
    {
        PurchaseOrder::factory()->count(25)->create();

        $response = $this->withBasicAuthCredentials()->get(route('purchase-orders.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('purchaseOrders.data', 20)
            ->where('purchaseOrders.total', 25));
    }

    public function test_create_page_shows_suppliers_and_suggestions(): void
    {
        $item = Item::factory()->create(['min_stock' => 10]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 3,
        ]);
        Supplier::factory()->create();

        $response = $this->withBasicAuthCredentials()->get(route('purchase-orders.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('PurchaseOrders/Create')
            ->has('suppliers', 1)
            ->has('suggestions', 1)
            ->where('suggestions.0.id', $item->id)
            ->where('suggestions.0.suggested_quantity', 7));
    }

    public function test_purchase_order_can_be_created_from_suggestions(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create(['purchase_price' => 12_000]);

        $response = $this->withBasicAuthCredentials()->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'notes' => 'Restok',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity_ordered' => 8,
                    'unit_price' => 11_000,
                ],
            ],
        ]);

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'quantity_ordered' => 8,
            'unit_price' => 11_000,
        ]);
    }

    public function test_duplicate_items_are_rejected_with_indonesian_message(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();

        $response = $this->withBasicAuthCredentials()->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => 1000],
                ['item_id' => $item->id, 'quantity_ordered' => 2, 'unit_price' => 1000],
            ],
        ]);

        $response->assertSessionHasErrors('lines.1.item_id');
        $this->assertStringContainsString(
            'tidak boleh dipilih lebih dari sekali',
            (string) session('errors')->first('lines.1.item_id'),
        );
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_show_page_displays_status_and_items(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
        $item = Item::factory()->create();
        $purchaseOrder->items()->create([
            'item_id' => $item->id,
            'quantity_ordered' => 5,
            'quantity_received' => 2,
            'unit_price' => 10_000,
        ]);

        $response = $this->withBasicAuthCredentials()->get(
            route('purchase-orders.show', $purchaseOrder),
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('PurchaseOrders/Show')
            ->where('purchaseOrder.status', PurchaseOrder::STATUS_ORDERED)
            ->has('purchaseOrder.items', 1)
            ->where('purchaseOrder.items.0.quantity_ordered', '5.00')
            ->where('purchaseOrder.items.0.quantity_received', '2.00'));
    }

    public function test_mark_ordered_action_updates_status(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.order', $purchaseOrder),
        );

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $response->assertSessionHas('success');
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $purchaseOrder->refresh()->status);
        $this->assertNotNull($purchaseOrder->ordered_on);
    }

    public function test_cancel_action_works_for_allowed_status(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->ordered()->create();

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.cancel', $purchaseOrder),
        );

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $response->assertSessionHas('success');
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $purchaseOrder->refresh()->status);
    }

    public function test_cancel_action_is_rejected_for_completed_purchase_order(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->completed()->create();

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.cancel', $purchaseOrder),
        );

        $response->assertSessionHas('error');
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $purchaseOrder->refresh()->status);
    }

    public function test_edit_is_only_available_for_draft(): void
    {
        $draft = PurchaseOrder::factory()->create();
        $ordered = PurchaseOrder::factory()->ordered()->create();

        $this->withBasicAuthCredentials()
            ->get(route('purchase-orders.edit', $draft))
            ->assertInertia(fn (Assert $page) => $page->component('PurchaseOrders/Create'));

        $this->withBasicAuthCredentials()
            ->get(route('purchase-orders.edit', $ordered))
            ->assertRedirect(route('purchase-orders.show', $ordered));
    }

    public function test_purchase_order_can_be_updated_via_http(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $item = Item::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->withBasicAuthCredentials()->put(
            route('purchase-orders.update', $purchaseOrder),
            [
                'supplier_id' => $supplier->id,
                'notes' => 'Diperbarui',
                'lines' => [
                    ['item_id' => $item->id, 'quantity_ordered' => 3, 'unit_price' => 5000],
                ],
            ],
        );

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'notes' => 'Diperbarui',
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
        ]);
    }
}
