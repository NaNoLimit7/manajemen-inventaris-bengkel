<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockDocument;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReportService
{
    /**
     * @param  array{search?: string|null, category_id?: int|string|null, is_active?: bool|string|null}  $filters
     * @return Collection<int, Item>
     */
    public function stockReport(array $filters = []): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $categoryId = $filters['category_id'] ?? null;
        $isActive = $filters['is_active'] ?? null;

        return Item::query()
            ->with('category')
            ->withSum('batches as current_stock', 'quantity_remaining')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(
                filled($categoryId),
                fn (Builder $query) => $query->where('category_id', $categoryId),
            )
            ->when(
                $isActive !== null && $isActive !== '',
                fn (Builder $query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN)),
            )
            ->orderBy('name')
            ->get()
            ->each(function (Item $item): void {
                $stock = bcadd((string) ($item->current_stock ?? '0'), '0', 2);

                $item->setAttribute('current_stock', $stock);
                $item->setAttribute(
                    'inventory_value',
                    bcmul($stock, (string) ($item->purchase_price ?? '0'), 2),
                );
            });
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, type?: string|null}  $filters
     * @return Collection<int, StockMovement>
     */
    public function stockCard(Item $item, array $filters = []): Collection
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $type = $filters['type'] ?? null;

        $runningBalance = '0.00';

        return StockMovement::query()
            ->where('item_id', $item->id)
            ->with(['stockDocument', 'stockBatch'])
            ->orderBy('id')
            ->get()
            ->map(function (StockMovement $movement) use (&$runningBalance): StockMovement {
                $runningBalance = bcadd($runningBalance, (string) $movement->quantity, 2);

                $movement->setAttribute('running_balance', $runningBalance);

                return $movement;
            })
            ->filter(function (StockMovement $movement) use ($dateFrom, $dateTo, $type): bool {
                $document = $movement->stockDocument;
                $occurredOn = $document?->occurred_on?->toDateString();

                if ($dateFrom !== null && $occurredOn !== null && $occurredOn < $dateFrom) {
                    return false;
                }

                if ($dateTo !== null && $occurredOn !== null && $occurredOn > $dateTo) {
                    return false;
                }

                return $type === null || $document?->type === $type;
            })
            ->values();
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, group_by?: string|null}  $filters
     * @return array{
     *     total_sales: float,
     *     transaction_count: int,
     *     group_by: string,
     *     date_from: string,
     *     date_to: string,
     *     series: array<int, array{period: string, total: float, transaction_count: int}>,
     *     items: array<int, array{item_id: int, item_name: string, item_code: string, unit: string, quantity: float, total: float}>,
     *     best_sellers: array<int, array{item_id: int, item_name: string, item_code: string, unit: string, quantity: float, total: float}>,
     *     customers: array<int, array{customer_id: int, customer_name: string, total: float, transaction_count: int}>
     * }
     */
    public function salesReport(array $filters = []): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($filters);
        $groupBy = ($filters['group_by'] ?? null) === 'month' ? 'month' : 'day';

        $sales = $this->salesDocumentsQuery($dateFrom, $dateTo)
            ->with(['stockMovements.item', 'reversedBy.stockMovements', 'customer'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $totalSales = '0.00';
        $transactionCount = 0;
        $series = [];
        $items = [];
        $customers = [];

        foreach ($sales as $sale) {
            $reversal = $sale->reversedBy;
            $isCancelled = $reversal !== null;

            $documentTotal = '0.00';

            foreach ($sale->stockMovements->concat($reversal?->stockMovements ?? collect()) as $movement) {
                $value = $this->movementSalesValue($movement);
                $documentTotal = bcadd($documentTotal, $value, 2);

                $itemId = $movement->item_id;
                $item = $movement->item;

                $items[$itemId] ??= [
                    'item_id' => $itemId,
                    'item_name' => $item?->name ?? 'Barang tidak ditemukan',
                    'item_code' => $item?->code ?? '-',
                    'unit' => $item?->unit ?? '',
                    'quantity' => '0.00',
                    'total' => '0.00',
                ];

                $items[$itemId]['quantity'] = bcadd(
                    $items[$itemId]['quantity'],
                    bcmul((string) $movement->quantity, '-1', 2),
                    2,
                );
                $items[$itemId]['total'] = bcadd($items[$itemId]['total'], $value, 2);
            }

            $totalSales = bcadd($totalSales, $documentTotal, 2);

            $period = $groupBy === 'month'
                ? $sale->occurred_on->format('Y-m')
                : $sale->occurred_on->toDateString();

            $series[$period] ??= [
                'period' => $period,
                'total' => '0.00',
                'transaction_count' => 0,
            ];
            $series[$period]['total'] = bcadd($series[$period]['total'], $documentTotal, 2);

            if ($isCancelled) {
                continue;
            }

            $transactionCount++;
            $series[$period]['transaction_count']++;

            if ($sale->customer_id === null) {
                continue;
            }

            $customers[$sale->customer_id] ??= [
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer?->name ?? 'Pelanggan tidak ditemukan',
                'total' => '0.00',
                'transaction_count' => 0,
            ];
            $customers[$sale->customer_id]['total'] = bcadd(
                $customers[$sale->customer_id]['total'],
                $documentTotal,
                2,
            );
            $customers[$sale->customer_id]['transaction_count']++;
        }

        $itemRows = collect($items)
            ->filter(fn (array $item): bool => bccomp($item['quantity'], '0', 2) !== 0 || bccomp($item['total'], '0', 2) !== 0)
            ->map(fn (array $item): array => [
                ...$item,
                'quantity' => (float) $item['quantity'],
                'total' => (float) $item['total'],
            ])
            ->sortByDesc('quantity')
            ->values();

        $seriesRows = collect($series)
            ->map(fn (array $row): array => [
                ...$row,
                'total' => (float) $row['total'],
            ])
            ->sortBy('period')
            ->values();

        $customerRows = collect($customers)
            ->map(fn (array $row): array => [
                ...$row,
                'total' => (float) $row['total'],
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'total_sales' => (float) $totalSales,
            'transaction_count' => $transactionCount,
            'group_by' => $groupBy,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'series' => $seriesRows->all(),
            'items' => $itemRows->all(),
            'best_sellers' => $itemRows->take(5)->values()->all(),
            'customers' => $customerRows->all(),
        ];
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{total_sales: float, transaction_count: int, date_from: string, date_to: string}
     */
    public function salesSummary(array $filters = []): array
    {
        [$dateFrom, $dateTo] = $this->dateRange($filters);

        $salesDocumentIds = $this->salesDocumentsQuery($dateFrom, $dateTo)->pluck('id');

        $salesValue = $this->movementValue($salesDocumentIds->all());

        $reversalDocumentIds = StockDocument::query()
            ->where('type', 'reversal')
            ->whereIn('reverses_document_id', $salesDocumentIds)
            ->pluck('id');

        $reversalValue = $this->movementValue($reversalDocumentIds->all());

        return [
            'total_sales' => (float) bcadd($salesValue, $reversalValue, 2),
            'transaction_count' => $this->salesDocumentsQuery($dateFrom, $dateTo)
                ->whereDoesntHave('reversedBy')
                ->count(),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{0: string, 1: string}
     */
    private function dateRange(array $filters): array
    {
        return [
            $filters['date_from'] ?? now()->startOfMonth()->toDateString(),
            $filters['date_to'] ?? now()->endOfMonth()->toDateString(),
        ];
    }

    private function salesDocumentsQuery(string $dateFrom, string $dateTo): Builder
    {
        return StockDocument::query()
            ->where('type', 'out')
            ->where('purpose', 'sold')
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('occurred_on', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('occurred_on', '<=', $dateTo));
    }

    private function movementSalesValue(StockMovement $movement): string
    {
        $price = (string) ($movement->unit_price ?? '0');

        return bcmul(
            bcmul((string) $movement->quantity, $price, 4),
            '-1',
            2,
        );
    }

    /**
     * @param  array<int, int>  $documentIds
     */
    private function movementValue(array $documentIds): string
    {
        if ($documentIds === []) {
            return '0.00';
        }

        $total = StockMovement::query()
            ->whereIn('stock_document_id', $documentIds)
            ->selectRaw('COALESCE(SUM(-quantity * COALESCE(unit_price, 0)), 0) as total')
            ->value('total');

        return bcadd((string) ($total ?? '0'), '0', 2);
    }
}
