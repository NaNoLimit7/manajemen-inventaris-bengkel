<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\StockBatch;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_detail_shows_purchase_history_and_total(): void
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 10,
        ]);

        $service = app(StockService::class);

        $service->issue([
            'occurred_on' => '2026-11-01',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50_000],
            ],
        ]);

        $service->issue([
            'occurred_on' => '2026-11-05',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 3, 'unit_price' => 40_000],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('customers.show', $customer));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Show')
            ->where('customer.id', $customer->id)
            ->has('history', 2)
            ->where('totalPurchases', 220_000)
            ->where('history.0.total', 120_000)
            ->where('history.1.total', 100_000)
            ->where('history.0.is_cancelled', false));
    }

    public function test_cancelled_sale_does_not_add_to_total(): void
    {
        $customer = Customer::factory()->create();
        $item = Item::factory()->create(['unit' => 'pcs']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 10,
        ]);

        $document = app(StockService::class)->issue([
            'occurred_on' => '2026-11-01',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50_000],
            ],
        ]);

        $reversal = StockDocument::factory()->create([
            'type' => 'reversal',
            'purpose' => null,
            'notes' => 'Batal jual',
            'reverses_document_id' => $document->id,
        ]);

        $batch->update(['quantity_remaining' => 10]);

        StockMovement::query()->create([
            'stock_document_id' => $reversal->id,
            'item_id' => $item->id,
            'stock_batch_id' => $batch->id,
            'quantity' => 2,
            'unit_price' => 50_000,
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('customers.show', $customer));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('history', 1)
            ->where('history.0.is_cancelled', true)
            ->where('history.0.total', 0)
            ->where('totalPurchases', 0));
    }

    public function test_customer_without_history_shows_empty_state(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->withBasicAuthCredentials()->get(route('customers.show', $customer));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('history', 0)
            ->where('totalPurchases', 0));
    }

    public function test_history_only_includes_the_selected_customer(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $item = Item::factory()->create();
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 10,
        ]);

        $service = app(StockService::class);

        $service->issue([
            'occurred_on' => '2026-11-01',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 10_000],
            ],
        ]);

        $service->issue([
            'occurred_on' => '2026-11-02',
            'purpose' => 'sold',
            'customer_id' => $otherCustomer->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 1, 'unit_price' => 99_000],
            ],
        ]);

        $response = $this->withBasicAuthCredentials()->get(route('customers.show', $customer));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('history', 1)
            ->where('totalPurchases', 10_000));
    }
}
