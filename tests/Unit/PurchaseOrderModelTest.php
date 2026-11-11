<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockDocument;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_purchase_order_item_relations_work_both_ways(): void
    {
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->for($supplier)->create();
        $item = Item::factory()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
        ]);

        $this->assertTrue($purchaseOrder->supplier->is($supplier));
        $this->assertTrue($supplier->purchaseOrders()->whereKey($purchaseOrder->getKey())->exists());
        $this->assertTrue($purchaseOrder->items()->whereKey($purchaseOrderItem->getKey())->exists());
        $this->assertTrue($purchaseOrderItem->purchaseOrder->is($purchaseOrder));
        $this->assertTrue($purchaseOrderItem->item->is($item));
        $this->assertTrue($item->purchaseOrderItems()->whereKey($purchaseOrderItem->getKey())->exists());
    }

    public function test_stock_document_belongs_to_supplier_customer_and_purchase_order(): void
    {
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->for($supplier)->create();

        $document = StockDocument::factory()->create([
            'supplier_id' => $supplier->id,
            'customer_id' => $customer->id,
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $this->assertTrue($document->supplier->is($supplier));
        $this->assertTrue($document->customer->is($customer));
        $this->assertTrue($document->purchaseOrder->is($purchaseOrder));
        $this->assertTrue($supplier->stockDocuments()->whereKey($document->getKey())->exists());
        $this->assertTrue($customer->stockDocuments()->whereKey($document->getKey())->exists());
    }

    public function test_money_and_quantity_columns_use_decimal_casts(): void
    {
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'quantity_ordered' => 12.5,
            'quantity_received' => 2.25,
            'unit_price' => 1500.5,
        ]);

        $this->assertSame('12.50', $purchaseOrderItem->quantity_ordered);
        $this->assertSame('2.25', $purchaseOrderItem->quantity_received);
        $this->assertSame('1500.50', $purchaseOrderItem->unit_price);
    }

    public function test_purchase_order_and_boolean_casts(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->ordered()->create([
            'ordered_on' => '2026-11-12',
        ]);
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $customer = Customer::factory()->inactive()->create();

        $this->assertTrue($purchaseOrder->ordered_on->isSameDay('2026-11-12'));
        $this->assertSame('2026-11-12', $purchaseOrder->ordered_on->toDateString());
        $this->assertTrue($purchaseOrder->status === PurchaseOrder::STATUS_ORDERED);
        $this->assertTrue($supplier->is_active);
        $this->assertFalse($customer->is_active);
    }

    public function test_active_scope_filters_inactive_records(): void
    {
        $activeSupplier = Supplier::factory()->create();
        Supplier::factory()->inactive()->create();
        $activeCustomer = Customer::factory()->create();
        Customer::factory()->inactive()->create();

        $this->assertTrue(Supplier::query()->active()->sole()->is($activeSupplier));
        $this->assertTrue(Customer::query()->active()->sole()->is($activeCustomer));
    }
}
