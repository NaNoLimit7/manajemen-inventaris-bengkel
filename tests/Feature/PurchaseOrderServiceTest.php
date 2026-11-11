<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Services\AlertService;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_draft_purchase_order_can_be_created(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();

        $purchaseOrder = app(PurchaseOrderService::class)->create([
            'supplier_id' => $supplier->id,
            'notes' => 'Restok bulanan',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity_ordered' => 10,
                    'unit_price' => 15_000,
                ],
            ],
        ]);

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->status);
        $this->assertSame($supplier->id, $purchaseOrder->supplier_id);
        $this->assertCount(1, $purchaseOrder->items);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $item->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_price' => 15_000,
        ]);
    }

    public function test_purchase_order_number_follows_format_and_monthly_sequence(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();

        $service = app(PurchaseOrderService::class);

        $first = $service->create([
            'supplier_id' => $supplier->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => 1000],
            ],
        ]);

        $second = $service->create([
            'supplier_id' => $supplier->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity_ordered' => 2, 'unit_price' => 1000],
            ],
        ]);

        $this->assertSame('PO-202611-0001', $first->number);
        $this->assertSame('PO-202611-0002', $second->number);
    }

    public function test_new_month_restarts_the_sequence(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();
        $service = app(PurchaseOrderService::class);

        $service->create([
            'supplier_id' => $supplier->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => 1000],
            ],
        ]);

        Carbon::setTestNow('2026-12-01 08:00:00');

        $nextMonth = $service->create([
            'supplier_id' => $supplier->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => 1000],
            ],
        ]);

        $this->assertSame('PO-202612-0001', $nextMonth->number);
    }

    public function test_purchase_order_requires_at_least_one_line(): void
    {
        $supplier = Supplier::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseOrderService::class)->create([
            'supplier_id' => $supplier->id,
            'lines' => [],
        ]);
    }

    public function test_duplicate_items_are_rejected(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseOrderService::class)->create([
            'supplier_id' => $supplier->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => 1000],
                ['item_id' => $item->id, 'quantity_ordered' => 2, 'unit_price' => 1000],
            ],
        ]);
    }

    public function test_quantity_and_unit_price_are_validated(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();
        $service = app(PurchaseOrderService::class);

        try {
            $service->create([
                'supplier_id' => $supplier->id,
                'lines' => [
                    ['item_id' => $item->id, 'quantity_ordered' => 0, 'unit_price' => 1000],
                ],
            ]);
            $this->fail('Expected invalid quantity exception.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('purchase_orders', 0);
        }

        $this->expectException(InvalidArgumentException::class);

        $service->create([
            'supplier_id' => $supplier->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => -1],
            ],
        ]);
    }

    public function test_mark_ordered_sets_date_and_status(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();

        app(PurchaseOrderService::class)->markOrdered($purchaseOrder);

        $purchaseOrder->refresh();

        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $purchaseOrder->status);
        $this->assertSame('2026-11-12', $purchaseOrder->ordered_on->toDateString());
    }

    public function test_mark_ordered_rejects_non_draft(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->completed()->create();

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseOrderService::class)->markOrdered($purchaseOrder);
    }

    public function test_draft_and_ordered_without_receipt_can_be_cancelled(): void
    {
        $service = app(PurchaseOrderService::class);

        $draft = PurchaseOrder::factory()->create();
        $service->cancel($draft);

        $ordered = PurchaseOrder::factory()->ordered()->create();
        $service->cancel($ordered);

        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $draft->refresh()->status);
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $ordered->refresh()->status);
    }

    public function test_ordered_purchase_order_with_receipt_cannot_be_cancelled(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
        $purchaseOrder->items()->create([
            'item_id' => Item::factory()->create()->id,
            'quantity_ordered' => 5,
            'quantity_received' => 2,
            'unit_price' => 1000,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseOrderService::class)->cancel($purchaseOrder);
    }

    public function test_completed_purchase_order_cannot_be_cancelled(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->completed()->create();

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseOrderService::class)->cancel($purchaseOrder);
    }

    public function test_update_only_allowed_for_draft_and_replaces_lines(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $originalItem = Item::factory()->create();
        $purchaseOrder->items()->create([
            'item_id' => $originalItem->id,
            'quantity_ordered' => 2,
            'quantity_received' => 0,
            'unit_price' => 1000,
        ]);

        $newSupplier = Supplier::factory()->create();
        $newItem = Item::factory()->create();

        app(PurchaseOrderService::class)->update($purchaseOrder, [
            'supplier_id' => $newSupplier->id,
            'notes' => 'Diperbarui',
            'lines' => [
                ['item_id' => $newItem->id, 'quantity_ordered' => 7, 'unit_price' => 2000],
            ],
        ]);

        $this->assertSame($newSupplier->id, $purchaseOrder->refresh()->supplier_id);
        $this->assertSame('Diperbarui', $purchaseOrder->notes);
        $this->assertFalse($purchaseOrder->items()->where('item_id', $originalItem->id)->exists());
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'item_id' => $newItem->id,
            'quantity_ordered' => 7,
        ]);
    }

    public function test_update_rejects_non_draft(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->ordered()->create();

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseOrderService::class)->update($purchaseOrder, [
            'supplier_id' => $purchaseOrder->supplier_id,
            'lines' => [],
        ]);
    }

    public function test_suggest_lines_uses_current_stock_against_minimum(): void
    {
        $item = Item::factory()->create(['min_stock' => 10]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 4,
        ]);

        $suggestions = app(PurchaseOrderService::class)->suggestLines();

        $this->assertCount(1, $suggestions);
        $this->assertSame($item->id, $suggestions[0]['id']);
        $this->assertSame(6.0, $suggestions[0]['suggested_quantity']);
    }

    public function test_create_retries_when_purchase_order_number_collides(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();
        PurchaseOrder::factory()->create(['number' => 'PO-202611-0001']);

        $service = new class(app(AlertService::class), app(StockService::class)) extends PurchaseOrderService
        {
            public int $calls = 0;

            /**
             * @var array<int, string>
             */
            public array $numbers = ['PO-202611-0001', 'PO-202611-0002'];

            protected function nextNumber(): string
            {
                return $this->numbers[$this->calls++];
            }
        };

        $purchaseOrder = $service->create([
            'supplier_id' => $supplier->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => 1000],
            ],
        ]);

        $this->assertSame('PO-202611-0002', $purchaseOrder->number);
        $this->assertSame(2, $service->calls);
        $this->assertDatabaseCount('purchase_orders', 2);
    }

    public function test_create_stops_retrying_after_three_number_collisions(): void
    {
        $supplier = Supplier::factory()->create();
        $item = Item::factory()->create();
        PurchaseOrder::factory()->create(['number' => 'PO-202611-0001']);

        $service = new class(app(AlertService::class), app(StockService::class)) extends PurchaseOrderService
        {
            public int $calls = 0;

            protected function nextNumber(): string
            {
                $this->calls++;

                return 'PO-202611-0001';
            }
        };

        try {
            $service->create([
                'supplier_id' => $supplier->id,
                'lines' => [
                    ['item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => 1000],
                ],
            ]);

            $this->fail('Expected duplicated purchase order number exception.');
        } catch (QueryException) {
            $this->assertSame(3, $service->calls);
            $this->assertDatabaseCount('purchase_orders', 1);
        }
    }
}
