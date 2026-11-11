<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_lists_active_items(): void
    {
        $activeItem = Item::factory()->create(['is_active' => true]);
        Item::factory()->create(['is_active' => false]);

        $response = $this->withBasicAuthCredentials()->get(route('stock.in.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/In')
            ->has('items', 1)
            ->where('items.0.id', $activeItem->id)
            ->where('items.0.has_expiry', (bool) $activeItem->has_expiry));
    }

    public function test_store_records_stock_in(): void
    {
        $item = Item::factory()->create([
            'has_expiry' => true,
            'purchase_price' => 1_000,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.in.store'),
            [
                'occurred_on' => '2026-11-10',
                'reference_number' => 'INV-001',
                'notes' => 'Penerimaan barang',
                'lines' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => 10,
                        'unit_cost' => 45_000,
                        'expiry_date' => '2027-01-01',
                    ],
                ],
            ],
        );

        $response->assertRedirect(route('stock.in.create'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'in',
            'reference_number' => 'INV-001',
            'occurred_on' => '2026-11-10',
        ]);
        $this->assertDatabaseHas('stock_batches', [
            'item_id' => $item->id,
            'expiry_date' => '2027-01-01',
            'quantity_remaining' => 10,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_cost' => 45_000,
        ]);
    }

    public function test_expiry_is_required_for_item_with_expiry(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.in.store'),
            $this->payload($item, ['expiry_date' => null]),
        );

        $response->assertSessionHasErrors('lines.0.expiry_date');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_expiry_is_forbidden_for_item_without_expiry(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.in.store'),
            $this->payload($item, ['expiry_date' => '2027-01-01']),
        );

        $response->assertSessionHasErrors('lines.0.expiry_date');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_inactive_item_is_rejected(): void
    {
        $item = Item::factory()->create([
            'is_active' => false,
            'has_expiry' => false,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.in.store'),
            $this->payload($item),
        );

        $response->assertSessionHasErrors('lines.0.item_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_quantity_must_be_greater_than_zero(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.in.store'),
            $this->payload($item, ['quantity' => 0]),
        );

        $response->assertSessionHasErrors('lines.0.quantity');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_past_expiry_date_is_allowed(): void
    {
        $item = Item::factory()->create(['has_expiry' => true]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.in.store'),
            $this->payload($item, ['expiry_date' => '2020-01-01']),
        );

        $response->assertRedirect(route('stock.in.create'));
        $this->assertDatabaseHas('stock_batches', [
            'item_id' => $item->id,
            'expiry_date' => '2020-01-01',
        ]);
    }

    public function test_create_page_lists_active_suppliers(): void
    {
        $activeSupplier = Supplier::factory()->create(['is_active' => true]);
        Supplier::factory()->inactive()->create();

        $response = $this->withBasicAuthCredentials()->get(route('stock.in.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('suppliers', 1)
            ->where('suppliers.0.id', $activeSupplier->id));
    }

    public function test_store_records_supplier_on_stock_in(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);
        $supplier = Supplier::factory()->create();

        $payload = $this->payload($item);
        $payload['supplier_id'] = $supplier->id;

        $response = $this->withBasicAuthCredentials()->post(route('stock.in.store'), $payload);

        $response->assertRedirect(route('stock.in.create'));
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'in',
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_inactive_supplier_is_rejected(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);
        $supplier = Supplier::factory()->inactive()->create();

        $payload = $this->payload($item);
        $payload['supplier_id'] = $supplier->id;

        $response = $this->withBasicAuthCredentials()->post(route('stock.in.store'), $payload);

        $response->assertSessionHasErrors('supplier_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_stock_in_can_be_saved_without_supplier(): void
    {
        $item = Item::factory()->create(['has_expiry' => false]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.in.store'),
            $this->payload($item),
        );

        $response->assertRedirect(route('stock.in.create'));
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'in',
            'supplier_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Item $item, array $overrides = []): array
    {
        return [
            'occurred_on' => '2026-11-10',
            'reference_number' => null,
            'notes' => null,
            'lines' => [
                array_merge([
                    'item_id' => $item->id,
                    'quantity' => 5,
                    'unit_cost' => 10_000,
                    'expiry_date' => $item->has_expiry ? '2027-01-01' : null,
                ], $overrides),
            ],
        ];
    }
}
