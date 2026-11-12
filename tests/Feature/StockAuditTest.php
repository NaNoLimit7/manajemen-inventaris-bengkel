<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\StockBatch;
use App\Models\StockDocument;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_creates_stock_adjusted_log(): void
    {
        $item = Item::factory()->create(['name' => 'Oli Mesin', 'unit' => 'pcs']);
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);

        $document = app(StockService::class)->adjust([
            'occurred_on' => '2026-11-12',
            'notes' => 'Stok opname',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'counted_quantity' => 8,
                ],
            ],
        ]);

        $log = $this->logFor($document, 'stock.adjusted');

        $this->assertNull($log->before);
        $this->assertSame('adjustment', $log->after['document']['type']);
        $this->assertSame('Stok opname', $log->after['document']['notes']);
        $this->assertSame('Oli Mesin', $log->after['movements'][0]['item_name']);
        $this->assertSame('3.00', $log->after['movements'][0]['quantity']);
    }

    public function test_reversal_creates_stock_reversed_log(): void
    {
        $item = Item::factory()->create(['name' => 'Busi', 'unit' => 'pcs']);
        $document = app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 20_000],
            ],
        ]);

        $reversal = app(StockService::class)->reverse($document, 'Salah input');

        $log = $this->logFor($reversal, 'stock.reversed');

        $this->assertNull($log->before);
        $this->assertSame('reversal', $log->after['document']['type']);
        $this->assertSame($document->id, $log->after['document']['reverses_document_id']);
        $this->assertSame('Salah input', $log->after['document']['notes']);
        $this->assertSame('Busi', $log->after['movements'][0]['item_name']);
        $this->assertSame('-5.00', $log->after['movements'][0]['quantity']);
    }

    public function test_regular_receive_and_issue_do_not_create_stock_audit_logs(): void
    {
        $item = Item::factory()->create(['unit' => 'pcs']);

        app(StockService::class)->receive([
            'occurred_on' => '2026-11-10',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 1_000],
            ],
        ]);

        app(StockService::class)->issue([
            'occurred_on' => '2026-11-11',
            'purpose' => 'workshop_use',
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $this->assertSame(
            0,
            AuditLog::query()->where('event', 'like', 'stock.%')->count(),
        );
    }

    public function test_failed_adjustment_leaves_no_audit_log(): void
    {
        $item = Item::factory()->create();
        $batch = StockBatch::factory()->for($item)->withoutExpiry()->create([
            'quantity_remaining' => 5,
        ]);
        $otherItem = Item::factory()->create();
        $otherBatch = StockBatch::factory()->for($otherItem)->withoutExpiry()->create([
            'quantity_remaining' => 3,
        ]);

        try {
            app(StockService::class)->adjust([
                'occurred_on' => '2026-11-12',
                'notes' => 'Opname',
                'lines' => [
                    [
                        'item_id' => $item->id,
                        'stock_batch_id' => $batch->id,
                        'counted_quantity' => 7,
                    ],
                    [
                        'item_id' => $item->id,
                        'stock_batch_id' => $otherBatch->id,
                        'counted_quantity' => 1,
                    ],
                ],
            ]);

            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->assertSame(
                0,
                AuditLog::query()->where('event', 'stock.adjusted')->count(),
            );
        }
    }

    private function logFor(StockDocument $document, string $event): AuditLog
    {
        return AuditLog::query()
            ->where('auditable_type', $document->getMorphClass())
            ->where('auditable_id', $document->getKey())
            ->where('event', $event)
            ->firstOrFail();
    }
}
