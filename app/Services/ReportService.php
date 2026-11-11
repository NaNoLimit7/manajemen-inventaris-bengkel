<?php

namespace App\Services;

use App\Models\StockDocument;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{total_sales: float, transaction_count: int, date_from: string, date_to: string}
     */
    public function salesSummary(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();

        $salesQuery = StockDocument::query()
            ->where('type', 'out')
            ->where('purpose', 'sold')
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('occurred_on', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('occurred_on', '<=', $dateTo));

        $salesDocumentIds = (clone $salesQuery)->pluck('id');

        $salesValue = $this->movementValue($salesDocumentIds->all());

        $reversalDocumentIds = StockDocument::query()
            ->where('type', 'reversal')
            ->whereIn('reverses_document_id', $salesDocumentIds)
            ->pluck('id');

        $reversalValue = $this->movementValue($reversalDocumentIds->all());

        return [
            'total_sales' => (float) bcadd($salesValue, $reversalValue, 2),
            'transaction_count' => (clone $salesQuery)->whereDoesntHave('reversedBy')->count(),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
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
