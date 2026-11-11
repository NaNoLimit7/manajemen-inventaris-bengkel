<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderReceiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_form_is_available_for_ordered_purchase_order(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
        $item = Item::factory()->create();
        $purchaseOrder->items()->create([
            'item_id' => $item->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_price' => 1000,
        ]);

        $response = $this->withBasicAuthCredentials()->get(
            route('purchase-orders.receive.form', $purchaseOrder),
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('PurchaseOrders/Receive')
            ->where('purchaseOrder.id', $purchaseOrder->id)
            ->has('purchaseOrder.items', 1));
    }

    public function test_receive_form_is_rejected_for_draft_purchase_order(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();

        $response = $this->withBasicAuthCredentials()->get(
            route('purchase-orders.receive.form', $purchaseOrder),
        );

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $response->assertSessionHas('error');
    }

    public function test_partial_receive_creates_stock_in_and_updates_status(): void
    {
        [$purchaseOrder, $poItem] = $this->orderedPurchaseOrder();

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['quantity' => 4]),
        );

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('stock_documents', [
            'type' => 'in',
            'supplier_id' => $purchaseOrder->supplier_id,
            'purchase_order_id' => $purchaseOrder->id,
        ]);
        $this->assertDatabaseHas('stock_batches', [
            'item_id' => $poItem->item_id,
            'quantity_remaining' => 4,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $poItem->item_id,
            'quantity' => 4,
            'unit_cost' => 1000,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'quantity_received' => 4,
        ]);
        $this->assertSame(
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            $purchaseOrder->refresh()->status,
        );
    }

    public function test_full_receive_marks_purchase_order_completed(): void
    {
        [$purchaseOrder, $poItem] = $this->orderedPurchaseOrder();

        $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['quantity' => 10]),
        );

        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $purchaseOrder->refresh()->status);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'quantity_received' => 10,
        ]);
    }

    public function test_second_partial_receive_completes_purchase_order(): void
    {
        [$purchaseOrder, $poItem] = $this->orderedPurchaseOrder();

        $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['quantity' => 4]),
        );

        $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['quantity' => 6]),
        );

        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $purchaseOrder->refresh()->status);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'quantity_received' => 10,
        ]);
        $this->assertDatabaseCount('stock_documents', 2);
    }

    public function test_quantity_above_remaining_is_rejected(): void
    {
        [$purchaseOrder, $poItem] = $this->orderedPurchaseOrder();

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['quantity' => 11]),
        );

        $response->assertSessionHasErrors('lines.0.quantity');
        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'quantity_received' => 0,
        ]);
    }

    public function test_receive_is_rejected_for_cancelled_purchase_order(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->cancelled()->create();
        $item = Item::factory()->create(['has_expiry' => false]);
        $poItem = $purchaseOrder->items()->create([
            'item_id' => $item->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_price' => 1000,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['quantity' => 1]),
        );

        $response->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_duplicate_purchase_order_item_lines_are_rejected(): void
    {
        [$purchaseOrder, $poItem] = $this->orderedPurchaseOrder();

        $payload = $this->payload($purchaseOrder, $poItem, ['quantity' => 2]);
        $payload['lines'][] = $payload['lines'][0];

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $payload,
        );

        $response->assertSessionHasErrors('lines.1.purchase_order_item_id');
        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'quantity_received' => 0,
        ]);
    }

    public function test_expiry_is_required_for_items_with_expiry(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);
        [$purchaseOrder, $poItem] = $this->orderedPurchaseOrder($item);

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['expiry_date' => null]),
        );

        $response->assertSessionHasErrors('lines.0.expiry_date');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_expiry_is_forbidden_for_items_without_expiry(): void
    {
        [$purchaseOrder, $poItem] = $this->orderedPurchaseOrder();

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['expiry_date' => '2027-01-01']),
        );

        $response->assertSessionHasErrors('lines.0.expiry_date');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_zero_quantity_lines_are_ignored(): void
    {
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->ordered()->for($supplier)->create();
        $expiryItem = Item::factory()->create(['has_expiry' => true]);
        $plainItem = Item::factory()->create(['has_expiry' => false]);

        $expiryLine = $purchaseOrder->items()->create([
            'item_id' => $expiryItem->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_price' => 1000,
        ]);
        $plainLine = $purchaseOrder->items()->create([
            'item_id' => $plainItem->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_price' => 2000,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            [
                'occurred_on' => '2026-11-12',
                'reference_number' => null,
                'notes' => null,
                'lines' => [
                    [
                        'purchase_order_item_id' => $expiryLine->id,
                        'quantity' => 0,
                        'unit_cost' => 1000,
                        'expiry_date' => null,
                    ],
                    [
                        'purchase_order_item_id' => $plainLine->id,
                        'quantity' => 2,
                        'unit_cost' => 2000,
                        'expiry_date' => null,
                    ],
                ],
            ],
        );

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('stock_documents', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $plainItem->id,
            'quantity' => 2,
            'unit_cost' => 2000,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $expiryLine->id,
            'quantity_received' => 0,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $plainLine->id,
            'quantity_received' => 2,
        ]);
    }

    public function test_all_zero_quantities_are_rejected(): void
    {
        [$purchaseOrder, $poItem] = $this->orderedPurchaseOrder();

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            $this->payload($purchaseOrder, $poItem, ['quantity' => 0]),
        );

        $response->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'quantity_received' => 0,
        ]);
    }

    public function test_validation_error_points_to_correct_line_with_zero_quantity(): void
    {
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->ordered()->for($supplier)->create();
        $zeroItem = Item::factory()->create(['has_expiry' => false]);
        $excessItem = Item::factory()->create(['has_expiry' => false]);

        $zeroLine = $purchaseOrder->items()->create([
            'item_id' => $zeroItem->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_price' => 1000,
        ]);
        $excessLine = $purchaseOrder->items()->create([
            'item_id' => $excessItem->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_price' => 1000,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('purchase-orders.receive.store', $purchaseOrder),
            [
                'occurred_on' => '2026-11-12',
                'reference_number' => null,
                'notes' => null,
                'lines' => [
                    [
                        'purchase_order_item_id' => $zeroLine->id,
                        'quantity' => 0,
                        'unit_cost' => 1000,
                        'expiry_date' => null,
                    ],
                    [
                        'purchase_order_item_id' => $excessLine->id,
                        'quantity' => 6,
                        'unit_cost' => 1000,
                        'expiry_date' => null,
                    ],
                ],
            ],
        );

        $response->assertSessionHasErrors('lines.1.quantity');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    /**
     * @return array{0: PurchaseOrder, 1: PurchaseOrderItem}
     */
    private function orderedPurchaseOrder(?Item $item = null): array
    {
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->ordered()->for($supplier)->create();
        $item ??= Item::factory()->create(['has_expiry' => false]);
        $poItem = $purchaseOrder->items()->create([
            'item_id' => $item->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_price' => 1000,
        ]);

        return [$purchaseOrder, $poItem];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(PurchaseOrder $purchaseOrder, PurchaseOrderItem $poItem, array $overrides = []): array
    {
        return [
            'occurred_on' => '2026-11-12',
            'reference_number' => 'INV-PO-001',
            'notes' => 'Penerimaan PO',
            'lines' => [
                array_merge([
                    'purchase_order_item_id' => $poItem->id,
                    'quantity' => 1,
                    'unit_cost' => 1000,
                    'expiry_date' => null,
                ], $overrides),
            ],
        ];
    }
}
