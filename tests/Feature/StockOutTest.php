<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Item;
use App\Models\StockBatch;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class StockOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_lists_active_items_with_stock(): void
    {
        $activeItem = Item::factory()->create([
            'is_active' => true,
            'sale_price' => 55_000,
        ]);
        StockBatch::factory()->for($activeItem)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        Item::factory()->create(['is_active' => false]);
        $customer = Customer::factory()->create(['is_active' => true]);
        Customer::factory()->inactive()->create();

        $response = $this->withBasicAuthCredentials()->get(route('stock.out.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Out')
            ->has('items', 1)
            ->where('items.0.id', $activeItem->id)
            ->where('items.0.current_stock', 5)
            ->where('items.0.sale_price', 55_000)
            ->has('customers', 1)
            ->where('customers.0.id', $customer->id));
    }

    public function test_store_records_stock_out_with_fefo(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        $earlyExpiry = StockBatch::factory()->for($item)->create([
            'expiry_date' => '2026-12-01',
            'quantity_remaining' => 5,
        ]);
        $noExpiry = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 10,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.out.store'),
            [
                'occurred_on' => '2026-11-10',
                'purpose' => 'service',
                'notes' => 'Servis rutin',
                'lines' => [
                    ['item_id' => $item->id, 'quantity' => 7],
                ],
            ],
        );

        $response->assertRedirect(route('stock.out.create'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'out',
            'purpose' => 'service',
            'notes' => 'Servis rutin',
        ]);
        $this->assertEqualsWithDelta(0, (float) $earlyExpiry->refresh()->quantity_remaining, 0.001);
        $this->assertEqualsWithDelta(8, (float) $noExpiry->refresh()->quantity_remaining, 0.001);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_insufficient_stock_is_rejected_before_service(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 2,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.out.store'),
            $this->payload($item, ['quantity' => 5]),
        );

        $response->assertSessionHasErrors('lines.0.quantity');
        $this->assertStringContainsString(
            'Tersisa 2.00 pcs.',
            (string) session('errors')->first('lines.0.quantity'),
        );
        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_inactive_item_is_rejected(): void
    {
        $item = Item::factory()->create([
            'is_active' => false,
            'unit' => 'pcs',
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.out.store'),
            $this->payload($item),
        );

        $response->assertSessionHasErrors('lines.0.item_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_quantity_must_be_greater_than_zero(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.out.store'),
            $this->payload($item, ['quantity' => 0]),
        );

        $response->assertSessionHasErrors('lines.0.quantity');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_sold_purpose_stores_unit_price_and_customer(): void
    {
        $item = Item::factory()->create([
            'unit' => 'pcs',
            'sale_price' => 60_000,
        ]);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        $customer = Customer::factory()->create(['is_active' => true]);

        $payload = $this->payload($item, ['quantity' => 2, 'unit_price' => 50_000]);
        $payload['purpose'] = 'sold';
        $payload['customer_id'] = $customer->id;

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertRedirect(route('stock.out.create'));
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'out',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'quantity' => -2,
            'unit_price' => 50_000,
        ]);
    }

    public function test_sold_without_customer_is_allowed(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $payload = $this->payload($item, ['unit_price' => 25_000]);
        $payload['purpose'] = 'sold';

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertRedirect(route('stock.out.create'));
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'out',
            'purpose' => 'sold',
            'customer_id' => null,
        ]);
    }

    public function test_sold_requires_unit_price(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $payload = $this->payload($item);
        $payload['purpose'] = 'sold';

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertSessionHasErrors('lines.0.unit_price');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_new_customer_name_creates_customer_with_sale(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $payload = $this->payload($item, ['unit_price' => 30_000]);
        $payload['purpose'] = 'sold';
        $payload['customer_name'] = 'Pelanggan Baru';

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertRedirect(route('stock.out.create'));

        $customer = Customer::query()->where('name', 'Pelanggan Baru')->first();

        $this->assertNotNull($customer);
        $this->assertDatabaseHas('stock_documents', [
            'type' => 'out',
            'purpose' => 'sold',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_new_customer_is_not_created_when_stock_recording_fails(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $this->mock(StockService::class, function (MockInterface $mock) use ($item): void {
            $mock->shouldReceive('issue')
                ->once()
                ->andThrow(InsufficientStockException::forItem($item, '0.00', 'pcs'));
        });

        $payload = $this->payload($item, ['unit_price' => 30_000]);
        $payload['purpose'] = 'sold';
        $payload['customer_name'] = 'Pelanggan Gagal';

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_inactive_customer_is_rejected(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        $customer = Customer::factory()->inactive()->create();

        $payload = $this->payload($item, ['unit_price' => 30_000]);
        $payload['purpose'] = 'sold';
        $payload['customer_id'] = $customer->id;

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_customer_and_unit_price_are_rejected_for_non_sale_purpose(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        $customer = Customer::factory()->create();

        $payload = $this->payload($item, ['unit_price' => 30_000]);
        $payload['customer_id'] = $customer->id;

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_customer_and_new_name_cannot_be_combined(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        $customer = Customer::factory()->create();

        $payload = $this->payload($item, ['unit_price' => 30_000]);
        $payload['purpose'] = 'sold';
        $payload['customer_id'] = $customer->id;
        $payload['customer_name'] = 'Nama Baru';

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertSessionHasErrors('customer_name');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_duplicate_item_lines_are_rejected(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 10,
        ]);

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.out.store'),
            [
                'occurred_on' => '2026-11-10',
                'purpose' => 'service',
                'lines' => [
                    ['item_id' => $item->id, 'quantity' => 1],
                    ['item_id' => $item->id, 'quantity' => 1],
                ],
            ],
        );

        $response->assertSessionHasErrors('lines.1.item_id');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_purpose_is_required(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $payload = $this->payload($item);
        unset($payload['purpose']);

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertSessionHasErrors('purpose');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    public function test_service_insufficient_stock_is_converted_to_validation_error(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $this->mock(StockService::class, function (MockInterface $mock) use ($item): void {
            $mock->shouldReceive('issue')
                ->once()
                ->andThrow(InsufficientStockException::forItem($item, '1.00', 'pcs'));
        });

        $response = $this->withBasicAuthCredentials()->post(
            route('stock.out.store'),
            $this->payload($item, ['quantity' => 2]),
        );

        $response->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('stock_documents', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Item $item, array $overrides = []): array
    {
        return [
            'occurred_on' => '2026-11-10',
            'purpose' => 'service',
            'notes' => null,
            'lines' => [
                array_merge([
                    'item_id' => $item->id,
                    'quantity' => 1,
                ], $overrides),
            ],
        ];
    }
}
