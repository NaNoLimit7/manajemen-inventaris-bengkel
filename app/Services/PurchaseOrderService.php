<?php

namespace App\Services;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockDocument;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PurchaseOrderService
{
    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        PurchaseOrder::STATUS_DRAFT => 'Draft',
        PurchaseOrder::STATUS_ORDERED => 'Dipesan',
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Diterima sebagian',
        PurchaseOrder::STATUS_COMPLETED => 'Selesai',
        PurchaseOrder::STATUS_CANCELLED => 'Dibatalkan',
    ];

    public function __construct(
        private readonly AlertService $alertService,
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  array{
     *     supplier_id: int,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         item_id: int,
     *         quantity_ordered: int|float|string,
     *         unit_price: int|float|string
     *     }>
     * }  $data
     */
    public function create(array $data): PurchaseOrder
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < 3) {
            $attempts++;

            try {
                return DB::transaction(function () use ($data): PurchaseOrder {
                    $supplier = $this->findActiveSupplier((int) $data['supplier_id']);
                    $lines = $this->validatedLines($data['lines'] ?? []);

                    $purchaseOrder = PurchaseOrder::query()->create([
                        'number' => $this->nextNumber(),
                        'supplier_id' => $supplier->id,
                        'status' => PurchaseOrder::STATUS_DRAFT,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    $purchaseOrder->items()->createMany($lines);

                    return $purchaseOrder->load('items.item');
                });
            } catch (QueryException $exception) {
                if (! $this->isDuplicateNumberException($exception)) {
                    throw $exception;
                }

                $lastException = $exception;
            }
        }

        throw $lastException ?? new RuntimeException('Gagal membuat nomor pesanan pembelian.');
    }

    /**
     * @param  array{
     *     supplier_id: int,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         item_id: int,
     *         quantity_ordered: int|float|string,
     *         unit_price: int|float|string
     *     }>
     * }  $data
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): void
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new InvalidArgumentException('Pesanan hanya dapat diubah saat berstatus draft.');
        }

        DB::transaction(function () use ($purchaseOrder, $data): void {
            $supplier = $this->findActiveSupplier((int) $data['supplier_id']);
            $lines = $this->validatedLines($data['lines'] ?? []);

            $purchaseOrder->update([
                'supplier_id' => $supplier->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $purchaseOrder->items()->delete();
            $purchaseOrder->items()->createMany($lines);
        });
    }

    public function markOrdered(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new InvalidArgumentException('Hanya pesanan draft yang dapat ditandai dipesan.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_on' => now()->toDateString(),
        ]);
    }

    public function cancel(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Pesanan ini sudah dibatalkan.');
        }

        if ($purchaseOrder->status === PurchaseOrder::STATUS_COMPLETED || $purchaseOrder->status === PurchaseOrder::STATUS_PARTIALLY_RECEIVED) {
            throw new InvalidArgumentException('Pesanan yang sudah diterima tidak dapat dibatalkan.');
        }

        $hasReceipt = $purchaseOrder->items()->where('quantity_received', '>', 0)->exists()
            || $purchaseOrder->stockDocuments()->exists();

        if ($hasReceipt) {
            throw new InvalidArgumentException('Pesanan yang sudah memiliki penerimaan tidak dapat dibatalkan.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CANCELLED,
        ]);
    }

    /**
     * @param  array{
     *     occurred_on: string,
     *     reference_number?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array{
     *         purchase_order_item_id: int,
     *         quantity: int|float|string,
     *         unit_cost: int|float|string,
     *         expiry_date?: string|null
     *     }>
     * }  $data
     */
    public function receive(PurchaseOrder $purchaseOrder, array $data): void
    {
        DB::transaction(function () use ($purchaseOrder, $data): void {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                throw new InvalidArgumentException('Penerimaan hanya dapat dicatat untuk pesanan yang sedang dipesan atau diterima sebagian.');
            }

            $poItems = $purchaseOrder->items()->get()->keyBy('id');
            $lines = $data['lines'] ?? [];

            if ($lines === []) {
                throw new InvalidArgumentException('Tambahkan minimal satu barang yang diterima.');
            }

            $stockLines = [];
            $receivedByLine = [];

            foreach ($lines as $line) {
                $poItem = $poItems->get((int) ($line['purchase_order_item_id'] ?? 0));

                if (! $poItem instanceof PurchaseOrderItem) {
                    throw new InvalidArgumentException('Barang pesanan tidak ditemukan pada pesanan ini.');
                }

                $quantity = bcadd((string) $line['quantity'], '0', 2);

                if (bccomp($quantity, '0', 2) < 0) {
                    throw new InvalidArgumentException('Jumlah diterima tidak boleh negatif.');
                }

                if (bccomp($quantity, '0', 2) === 0) {
                    continue;
                }

                if (isset($receivedByLine[$poItem->id])) {
                    throw new InvalidArgumentException('Barang yang sama tidak boleh dipilih lebih dari sekali.');
                }

                $remaining = bcsub((string) $poItem->quantity_ordered, (string) $poItem->quantity_received, 2);

                if (bccomp($quantity, $remaining, 2) > 0) {
                    throw new InvalidArgumentException(
                        sprintf('Jumlah diterima melebihi sisa pesanan %s.', $poItem->item?->name ?? 'barang'),
                    );
                }

                $stockLines[] = [
                    'item_id' => $poItem->item_id,
                    'quantity' => $quantity,
                    'unit_cost' => bcadd((string) $line['unit_cost'], '0', 2),
                    'expiry_date' => $line['expiry_date'] ?? null,
                ];

                $receivedByLine[$poItem->id] = $quantity;
            }

            if ($stockLines === []) {
                throw new InvalidArgumentException('Isi minimal satu jumlah diterima lebih dari 0.');
            }

            $this->stockService->receive([
                'occurred_on' => $data['occurred_on'],
                'reference_number' => $data['reference_number'] ?? null,
                'supplier_id' => $purchaseOrder->supplier_id,
                'purchase_order_id' => $purchaseOrder->id,
                'notes' => $data['notes'] ?? null,
                'lines' => $stockLines,
            ]);

            foreach ($receivedByLine as $poItemId => $quantity) {
                $poItem = $poItems->get($poItemId);
                $poItem->quantity_received = bcadd((string) $poItem->quantity_received, $quantity, 2);
                $poItem->save();
            }

            $this->refreshStatus($purchaseOrder);
        });
    }

    public function handleReceiptReversal(StockDocument $document): void
    {
        if ($document->purchase_order_id === null) {
            return;
        }

        $purchaseOrder = PurchaseOrder::query()
            ->lockForUpdate()
            ->find($document->purchase_order_id);

        if ($purchaseOrder === null) {
            return;
        }

        $receivedByItem = $document->stockMovements()
            ->selectRaw('item_id, SUM(quantity) as total_quantity')
            ->groupBy('item_id')
            ->pluck('total_quantity', 'item_id');

        foreach ($receivedByItem as $itemId => $quantity) {
            $poItem = $purchaseOrder->items()
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();

            if ($poItem === null) {
                continue;
            }

            $remaining = bcsub((string) $poItem->quantity_received, (string) $quantity, 2);

            if (bccomp($remaining, '0', 2) < 0) {
                $remaining = '0.00';
            }

            $poItem->quantity_received = $remaining;
            $poItem->save();
        }

        $this->refreshStatus($purchaseOrder);
    }

    public function refreshStatus(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
            return;
        }

        $items = $purchaseOrder->items()->get();

        $allReceived = $items->every(
            fn (PurchaseOrderItem $item): bool => bccomp(
                (string) $item->quantity_received,
                (string) $item->quantity_ordered,
                2,
            ) >= 0,
        );

        $anyReceived = $items->contains(
            fn (PurchaseOrderItem $item): bool => bccomp((string) $item->quantity_received, '0', 2) > 0,
        );

        $status = match (true) {
            $allReceived && $items->isNotEmpty() => PurchaseOrder::STATUS_COMPLETED,
            $anyReceived => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            $purchaseOrder->status === PurchaseOrder::STATUS_DRAFT => PurchaseOrder::STATUS_DRAFT,
            default => PurchaseOrder::STATUS_ORDERED,
        };

        $purchaseOrder->update(['status' => $status]);
    }

    public function formatText(PurchaseOrder $purchaseOrder): string
    {
        $purchaseOrder->loadMissing(['supplier', 'items.item']);

        $date = $purchaseOrder->ordered_on ?? $purchaseOrder->created_at;

        $lines = [
            'PESANAN PEMBELIAN',
            'Nomor: '.$purchaseOrder->number,
            'Tanggal: '.$date?->format('d/m/Y'),
            'Supplier: '.($purchaseOrder->supplier?->name ?? 'Supplier tidak ditemukan'),
            'Status: '.(self::STATUS_LABELS[$purchaseOrder->status] ?? $purchaseOrder->status),
            '',
            'Daftar Barang:',
        ];

        $total = '0.00';

        foreach ($purchaseOrder->items as $index => $line) {
            $subtotal = bcmul((string) $line->quantity_ordered, (string) $line->unit_price, 2);
            $total = bcadd($total, $subtotal, 2);

            $lines[] = sprintf(
                '%d. %s (%s)',
                $index + 1,
                $line->item?->name ?? 'Barang tidak ditemukan',
                $line->item?->code ?? '-',
            );
            $lines[] = sprintf(
                '   %s %s x %s = %s',
                $this->formatQuantityText($line->quantity_ordered),
                $line->item?->unit ?? '',
                $this->formatMoney($line->unit_price),
                $this->formatMoney($subtotal),
            );
        }

        $lines[] = '';
        $lines[] = 'Total: '.$this->formatMoney($total);
        $lines[] = '';
        $lines[] = 'Catatan: '.($purchaseOrder->notes ?: '-');

        return implode("\n", $lines);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function suggestLines(): Collection
    {
        return $this->alertService->lowStockItems()
            ->map(function (Item $item): array {
                $stock = bcadd((string) ($item->current_stock ?? '0'), '0', 2);
                $minimum = bcadd((string) $item->min_stock, '0', 2);
                $suggested = bcsub($minimum, $stock, 2);

                if (bccomp($suggested, '0', 2) < 0) {
                    $suggested = '0.00';
                }

                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'current_stock' => (float) $stock,
                    'min_stock' => (float) $minimum,
                    'suggested_quantity' => (float) $suggested,
                    'purchase_price' => (float) $item->purchase_price,
                ];
            })
            ->values();
    }

    private function formatMoney(mixed $value): string
    {
        return 'Rp'.number_format((float) $value, 2, ',', '.');
    }

    private function formatQuantityText(mixed $value): string
    {
        $formatted = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    private function findActiveSupplier(int $supplierId): Supplier
    {
        $supplier = Supplier::query()
            ->whereKey($supplierId)
            ->where('is_active', true)
            ->first();

        if ($supplier === null) {
            throw new InvalidArgumentException('Supplier tidak ditemukan atau tidak aktif.');
        }

        return $supplier;
    }

    /**
     * @param  array<int, array{
     *     item_id: int,
     *     quantity_ordered: int|float|string,
     *     unit_price: int|float|string
     * }>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function validatedLines(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Tambahkan minimal satu barang pada pesanan.');
        }

        $itemIds = [];

        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $quantity = bcadd((string) ($line['quantity_ordered'] ?? '0'), '0', 2);
            $unitPrice = bcadd((string) ($line['unit_price'] ?? '0'), '0', 2);

            if (isset($itemIds[$itemId])) {
                throw new InvalidArgumentException('Barang yang sama tidak boleh dipilih lebih dari sekali.');
            }

            if ($itemId <= 0 || ! Item::query()->whereKey($itemId)->exists()) {
                throw new InvalidArgumentException('Barang tidak ditemukan.');
            }

            if (bccomp($quantity, '0', 2) <= 0) {
                throw new InvalidArgumentException('Jumlah pesanan harus lebih dari 0.');
            }

            if (bccomp($unitPrice, '0', 2) < 0) {
                throw new InvalidArgumentException('Harga satuan tidak boleh negatif.');
            }

            $itemIds[$itemId] = true;
        }

        return array_map(fn (array $line): array => [
            'item_id' => (int) $line['item_id'],
            'quantity_ordered' => bcadd((string) $line['quantity_ordered'], '0', 2),
            'quantity_received' => '0.00',
            'unit_price' => bcadd((string) $line['unit_price'], '0', 2),
        ], $lines);
    }

    private function isDuplicateNumberException(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        if ($sqlState !== '23000' || $driverCode !== 1062) {
            return false;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'purchase_orders_number_unique')
            || str_contains($message, 'purchase_orders.number');
    }

    protected function nextNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';

        $lastNumber = PurchaseOrder::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('number');

        $sequence = 1;

        if (is_string($lastNumber) && str_starts_with($lastNumber, $prefix)) {
            $sequence = (int) substr($lastNumber, strlen($prefix)) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
