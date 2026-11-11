<?php

namespace App\Http\Controllers;

use App\Models\StockBatch;
use App\Services\AlertService;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const ALERT_LIMIT = 10;

    public function index(AlertService $alertService, ReportService $reportService): Response
    {
        return Inertia::render('Dashboard', [
            'summary' => $alertService->counts(),
            'salesSummary' => $reportService->salesSummary(),
            'lowStockItems' => $alertService->lowStockItems()
                ->take(self::ALERT_LIMIT)
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'current_stock' => (float) ($item->current_stock ?? 0),
                    'min_stock' => (float) $item->min_stock,
                ])
                ->values(),
            'expiringBatches' => $this->batchRows($alertService->expiringBatches()),
            'expiredBatches' => $this->batchRows($alertService->expiredBatches()),
        ]);
    }

    /**
     * @param  EloquentCollection<int, StockBatch>  $batches
     * @return Collection<int, array<string, mixed>>
     */
    private function batchRows(EloquentCollection $batches): Collection
    {
        return $batches
            ->take(self::ALERT_LIMIT)
            ->map(fn (StockBatch $batch): array => [
                'id' => $batch->id,
                'item_id' => $batch->item_id,
                'item_name' => $batch->item?->name,
                'item_code' => $batch->item?->code,
                'unit' => $batch->item?->unit,
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'quantity_remaining' => (float) $batch->quantity_remaining,
            ])
            ->values();
    }
}
