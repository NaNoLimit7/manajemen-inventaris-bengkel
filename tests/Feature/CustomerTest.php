<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\StockDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_customers_with_pagination(): void
    {
        Customer::factory()->count(25)->create();

        $response = $this->withBasicAuthCredentials()->get(route('customers.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->has('customers.data', 20)
            ->where('customers.total', 25));
    }

    public function test_index_supports_search(): void
    {
        $target = Customer::factory()->create(['name' => 'Budi Santoso']);
        Customer::factory()->create(['name' => 'Siti Aminah']);

        $response = $this->withBasicAuthCredentials()->get(route('customers.index', [
            'search' => 'Budi',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $target->id));
    }

    public function test_create_and_edit_pages_are_available(): void
    {
        $customer = Customer::factory()->create();

        $this->withBasicAuthCredentials()
            ->get(route('customers.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Customers/Create'));

        $this->withBasicAuthCredentials()
            ->get(route('customers.edit', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customers/Edit')
                ->where('customer.id', $customer->id));
    }

    public function test_customer_can_be_created(): void
    {
        $response = $this->withBasicAuthCredentials()->post(route('customers.store'), [
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'notes' => 'Pelanggan setia',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('customers', [
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
        ]);
    }

    public function test_customer_name_is_required(): void
    {
        $response = $this->withBasicAuthCredentials()->post(route('customers.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_can_be_updated_and_deactivated(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Nama Lama',
            'is_active' => true,
        ]);

        $response = $this->withBasicAuthCredentials()->put(route('customers.update', $customer), [
            'name' => 'Nama Baru',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Nama Baru',
            'is_active' => false,
        ]);
    }

    public function test_customer_with_sales_history_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->create();
        StockDocument::factory()->create([
            'type' => 'out',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
        ]);

        $response = $this->withBasicAuthCredentials()->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_without_history_can_be_deleted(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->withBasicAuthCredentials()->delete(route('customers.destroy', $customer));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
