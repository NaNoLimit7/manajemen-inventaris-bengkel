<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
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
        $activeItem = Item::factory()->create(['is_active' => true]);
        StockBatch::factory()->for($activeItem)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        Item::factory()->create(['is_active' => false]);

        $response = $this->withBasicAuthCredentials()->get(route('stock.out.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Out')
            ->has('items', 1)
            ->where('items.0.id', $activeItem->id)
            ->where('items.0.current_stock', 5));
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

    public function test_sold_purpose_is_not_available_in_m1(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);
        StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $payload = $this->payload($item);
        $payload['purpose'] = 'sold';

        $response = $this->withBasicAuthCredentials()->post(route('stock.out.store'), $payload);

        $response->assertSessionHasErrors('purpose');
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
