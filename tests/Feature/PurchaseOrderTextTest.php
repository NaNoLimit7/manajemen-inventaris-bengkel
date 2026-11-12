<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_formatted_text_contains_order_details_and_total(): void
    {
        $purchaseOrder = $this->purchaseOrder([
            'number' => 'PO-202611-0001',
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_on' => '2026-11-12',
            'notes' => 'Restok bulanan',
        ]);

        $text = app(PurchaseOrderService::class)->formatText($purchaseOrder);

        $this->assertStringContainsString('PESANAN PEMBELIAN', $text);
        $this->assertStringContainsString('Nomor: PO-202611-0001', $text);
        $this->assertStringContainsString('Tanggal: 12/11/2026', $text);
        $this->assertStringContainsString('Supplier: Toko Oli', $text);
        $this->assertStringContainsString('Status: Dipesan', $text);
        $this->assertStringContainsString('1. Oli Mesin (BRG-OLI)', $text);
        $this->assertStringContainsString('8 botol x Rp11.000,00 = Rp88.000,00', $text);
        $this->assertStringContainsString('2. Busi (BRG-BUSI)', $text);
        $this->assertStringContainsString('2 pcs x Rp5.000,00 = Rp10.000,00', $text);
        $this->assertStringContainsString('Total: Rp98.000,00', $text);
        $this->assertStringContainsString('Catatan: Restok bulanan', $text);
    }

    public function test_formatted_text_works_for_other_statuses(): void
    {
        $completed = $this->purchaseOrder(['status' => PurchaseOrder::STATUS_COMPLETED], 'A');
        $cancelled = $this->purchaseOrder(['status' => PurchaseOrder::STATUS_CANCELLED], 'B');

        $service = app(PurchaseOrderService::class);

        $this->assertStringContainsString('Status: Selesai', $service->formatText($completed));
        $this->assertStringContainsString('Status: Dibatalkan', $service->formatText($cancelled));
    }

    public function test_show_page_receives_formatted_text(): void
    {
        $purchaseOrder = $this->purchaseOrder(['number' => 'PO-202611-0099']);

        $response = $this->withBasicAuthCredentials()->get(
            route('purchase-orders.show', $purchaseOrder),
        );

        $response->assertInertia(fn (Assert $page) => $page
            ->component('PurchaseOrders/Show')
            ->has('text')
            ->where('purchaseOrder.number', 'PO-202611-0099'));

        $page = $response->viewData('page');

        $this->assertStringContainsString('Nomor: PO-202611-0099', $page['props']['text']);
        $this->assertStringContainsString('Total: Rp98.000,00', $page['props']['text']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function purchaseOrder(array $overrides = [], string $codeSuffix = ''): PurchaseOrder
    {
        $supplier = Supplier::factory()->create(['name' => 'Toko Oli']);
        $oli = Item::factory()->create([
            'name' => 'Oli Mesin',
            'code' => 'BRG-OLI'.$codeSuffix,
            'unit' => 'botol',
        ]);
        $busi = Item::factory()->create([
            'name' => 'Busi',
            'code' => 'BRG-BUSI'.$codeSuffix,
            'unit' => 'pcs',
        ]);

        $purchaseOrder = PurchaseOrder::factory()->for($supplier)->create(array_merge([
            'number' => 'PO-'.now()->format('Ym').'-'.fake()->unique()->numerify('####'),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'notes' => 'Restok bulanan',
        ], $overrides));

        $purchaseOrder->items()->createMany([
            [
                'item_id' => $oli->id,
                'quantity_ordered' => 8,
                'quantity_received' => 0,
                'unit_price' => 11_000,
            ],
            [
                'item_id' => $busi->id,
                'quantity_ordered' => 2,
                'quantity_received' => 0,
                'unit_price' => 5_000,
            ],
        ]);

        return $purchaseOrder->fresh(['supplier', 'items.item']);
    }
}
