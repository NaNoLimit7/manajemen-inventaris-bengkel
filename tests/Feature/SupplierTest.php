<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\StockDocument;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_suppliers_with_pagination(): void
    {
        Supplier::factory()->count(25)->create();

        $response = $this->withBasicAuthCredentials()->get(route('suppliers.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Suppliers/Index')
            ->has('suppliers.data', 20)
            ->where('suppliers.total', 25));
    }

    public function test_index_supports_search(): void
    {
        $target = Supplier::factory()->create(['name' => 'Toko Oli Jaya']);
        Supplier::factory()->create(['name' => 'Bengkel Lain']);

        $response = $this->withBasicAuthCredentials()->get(route('suppliers.index', [
            'search' => 'Oli Jaya',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.id', $target->id));
    }

    public function test_create_and_edit_pages_are_available(): void
    {
        $supplier = Supplier::factory()->create();

        $this->withBasicAuthCredentials()
            ->get(route('suppliers.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Suppliers/Create'));

        $this->withBasicAuthCredentials()
            ->get(route('suppliers.edit', $supplier))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Suppliers/Edit')
                ->where('supplier.id', $supplier->id));
    }

    public function test_supplier_can_be_created(): void
    {
        $response = $this->withBasicAuthCredentials()->post(route('suppliers.store'), [
            'name' => 'Toko Oli Jaya',
            'contact' => '08123456789',
            'notes' => 'Supplier oli',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Toko Oli Jaya',
            'contact' => '08123456789',
            'is_active' => true,
        ]);
    }

    public function test_supplier_name_is_required(): void
    {
        $response = $this->withBasicAuthCredentials()->post(route('suppliers.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_supplier_can_be_updated_and_deactivated(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Nama Lama',
            'is_active' => true,
        ]);

        $response = $this->withBasicAuthCredentials()->put(route('suppliers.update', $supplier), [
            'name' => 'Nama Baru',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Nama Baru',
            'is_active' => false,
        ]);
    }

    public function test_supplier_with_history_cannot_be_deleted(): void
    {
        $supplier = Supplier::factory()->create();
        StockDocument::factory()->create([
            'type' => 'in',
            'supplier_id' => $supplier->id,
        ]);

        $response = $this->withBasicAuthCredentials()->delete(route('suppliers.destroy', $supplier));

        $response->assertRedirect(route('suppliers.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_with_purchase_order_cannot_be_deleted(): void
    {
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->for($supplier)->create();

        $response = $this->withBasicAuthCredentials()->delete(route('suppliers.destroy', $supplier));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_without_history_can_be_deleted(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->withBasicAuthCredentials()->delete(route('suppliers.destroy', $supplier));

        $response->assertRedirect(route('suppliers.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
