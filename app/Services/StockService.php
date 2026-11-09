<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Item;
use App\Models\StockBatch;
use App\Models\StockDocument;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Record an incoming stock document and update batches.
     *
     * @param  array{
     *     occurred_on: string,
     *     reference_number?: string|null,
     *     supplier_id?: int|null,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         item_id: int,
     *         quantity: int|float|string,
     *         unit_cost: int|float|string,
     *         expiry_date?: string|null
     *     }>
     * }  $data
     */
    public function receive(array $data): StockDocument
    {
        return DB::transaction(function () use ($data): StockDocument {
            $document = StockDocument::query()->create([
                'type' => 'in',
                'occurred_on' => $data['occurred_on'],
                'reference_number' => $data['reference_number'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $item = Item::query()
                    ->lockForUpdate()
                    ->findOrFail($line['item_id']);

                $expiryDate = $line['expiry_date'] ?? null;
                $expiryDate = $expiryDate === '' ? null : $expiryDate;

                $batch = StockBatch::query()
                    ->where('item_id', $item->id)
                    ->when(
                        $expiryDate === null,
                        fn ($query) => $query->whereNull('expiry_date'),
                        fn ($query) => $query->where('expiry_date', $expiryDate),
                    )
                    ->lockForUpdate()
                    ->first();

                if ($batch === null) {
                    $batch = StockBatch::query()->create([
                        'item_id' => $item->id,
                        'expiry_date' => $expiryDate,
                        'quantity_remaining' => 0,
                        'received_on' => $data['occurred_on'],
                    ]);
                }

                $batch->increment('quantity_remaining', $line['quantity']);

                StockMovement::query()->create([
                    'stock_document_id' => $document->id,
                    'item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ]);

                $item->update([
                    'purchase_price' => $line['unit_cost'],
                ]);
            }

            return $document->load('stockMovements');
        });
    }

    /**
     * Record an outgoing stock document using FEFO.
     *
     * @param  array{
     *     occurred_on: string,
     *     purpose: string,
     *     customer_id?: int|null,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         item_id: int,
     *         quantity: int|float|string,
     *         unit_price?: int|float|string|null
     *     }>
     * }  $data
     */
    public function issue(array $data): StockDocument
    {
        return DB::transaction(function () use ($data): StockDocument {
            $document = StockDocument::query()->create([
                'type' => 'out',
                'purpose' => $data['purpose'],
                'occurred_on' => $data['occurred_on'],
                'customer_id' => $data['customer_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $item = Item::query()
                    ->lockForUpdate()
                    ->findOrFail($line['item_id']);

                $remaining = bcadd((string) $line['quantity'], '0', 2);

                $batches = StockBatch::query()
                    ->where('item_id', $item->id)
                    ->where('quantity_remaining', '>', 0)
                    ->orderByRaw('expiry_date IS NULL')
                    ->orderBy('expiry_date')
                    ->orderBy('received_on')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $available = '0.00';

                foreach ($batches as $batch) {
                    $available = bcadd($available, (string) $batch->quantity_remaining, 2);
                }

                if (bccomp($available, $remaining, 2) < 0) {
                    throw InsufficientStockException::forItem($item, $available, $item->unit);
                }

                foreach ($batches as $batch) {
                    if (bccomp($remaining, '0', 2) <= 0) {
                        break;
                    }

                    $batchQuantity = (string) $batch->quantity_remaining;
                    $take = bccomp($batchQuantity, $remaining, 2) <= 0 ? $batchQuantity : $remaining;

                    if (bccomp($take, '0', 2) <= 0) {
                        continue;
                    }

                    $batch->quantity_remaining = bcsub($batchQuantity, $take, 2);
                    $batch->save();

                    StockMovement::query()->create([
                        'stock_document_id' => $document->id,
                        'item_id' => $item->id,
                        'stock_batch_id' => $batch->id,
                        'quantity' => bcmul($take, '-1', 2),
                        'unit_price' => $data['purpose'] === 'sold'
                            ? ($line['unit_price'] ?? $item->sale_price)
                            : null,
                    ]);

                    $remaining = bcsub($remaining, $take, 2);
                }

                if (bccomp($remaining, '0', 2) > 0) {
                    throw InsufficientStockException::forItem($item, $available, $item->unit);
                }
            }

            return $document->load('stockMovements');
        });
    }
}
