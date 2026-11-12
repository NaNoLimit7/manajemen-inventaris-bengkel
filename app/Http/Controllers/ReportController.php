<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBatch;
use App\Services\AlertService;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function stock(Request $request, ReportService $reportService): Response
    {
        $search = trim((string) $request->string('search'));
        $categoryId = $request->integer('category_id') ?: null;
        $isActive = $request->has('is_active') ? $request->string('is_active')->toString() : null;

        $filters = [
            'search' => $search,
            'category_id' => $categoryId,
            'is_active' => $isActive,
        ];

        $items = $reportService->stockReport($filters);

        $totalValue = $items->reduce(
            fn (string $carry, Item $item): string => bcadd($carry, (string) $item->inventory_value, 2),
            '0.00',
        );

        return Inertia::render('Reports/Stock', [
            'items' => $items,
            'categories' => Category::query()
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug']),
            'summary' => [
                'item_count' => $items->count(),
                'total_inventory_value' => $totalValue,
            ],
            'filters' => $filters,
        ]);
    }

    public function alerts(AlertService $alertService): Response
    {
        return Inertia::render('Reports/Alerts', [
            'counts' => $alertService->counts(),
            'lowStockItems' => $alertService->lowStockItems()
                ->map(fn (Item $item): array => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'current_stock' => bcadd((string) ($item->current_stock ?? '0'), '0', 2),
                    'min_stock' => bcadd((string) $item->min_stock, '0', 2),
                ])
                ->values(),
            'expiringBatches' => $this->batchRows($alertService->expiringBatches()),
            'expiredBatches' => $this->batchRows($alertService->expiredBatches()),
        ]);
    }

    public function sales(Request $request, ReportService $reportService): Response
    {
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));
        $groupBy = $request->string('group_by')->toString() === 'month' ? 'month' : 'day';

        $report = $reportService->salesReport([
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'group_by' => $groupBy,
        ]);

        return Inertia::render('Reports/Sales', [
            'report' => $report,
            'filters' => [
                'date_from' => $dateFrom !== '' ? $dateFrom : $report['date_from'],
                'date_to' => $dateTo !== '' ? $dateTo : $report['date_to'],
                'group_by' => $groupBy,
            ],
        ]);
    }

    public function stockCard(Request $request, Item $item, ReportService $reportService): Response
    {
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));
        $type = trim((string) $request->string('type'));

        $filters = [
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'type' => $type !== '' ? $type : null,
        ];

        $movements = $reportService->stockCard($item, $filters);

        $item->load('category');
        $item->setAttribute(
            'current_stock',
            bcadd((string) $item->batches()->sum('quantity_remaining'), '0', 2),
        );

        return Inertia::render('Reports/StockCard', [
            'item' => $item,
            'movements' => $movements,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'type' => $type,
            ],
        ]);
    }

    /**
     * @param  EloquentCollection<int, StockBatch>  $batches
     * @return Collection<int, array<string, mixed>>
     */
    private function batchRows(EloquentCollection $batches): Collection
    {
        return $batches
            ->map(fn (StockBatch $batch): array => [
                'id' => $batch->id,
                'item_id' => $batch->item_id,
                'item_name' => $batch->item?->name,
                'item_code' => $batch->item?->code,
                'unit' => $batch->item?->unit,
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'quantity_remaining' => bcadd((string) $batch->quantity_remaining, '0', 2),
            ])
            ->values();
    }
}
