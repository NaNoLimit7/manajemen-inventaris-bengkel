<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class StockAdjustmentController extends Controller
{
    public function create(): Response
    {
        $items = Item::query()
            ->active()
            ->with(['batches' => fn ($query) => $query->orderBy('expiry_date')->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'unit' => $item->unit,
                'has_expiry' => $item->has_expiry,
                'current_stock' => (float) $item->batches->sum(
                    fn ($batch): float => (float) $batch->quantity_remaining,
                ),
                'batches' => $item->batches
                    ->map(fn ($batch): array => [
                        'id' => $batch->id,
                        'expiry_date' => $batch->expiry_date?->toDateString(),
                        'quantity_remaining' => (float) $batch->quantity_remaining,
                    ])
                    ->values(),
            ]);

        return Inertia::render('Stock/Adjustment', [
            'items' => $items,
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request, StockService $stockService): RedirectResponse
    {
        try {
            $stockService->adjust($request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'lines' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('stock.adjustments.create')
            ->with('success', 'Penyesuaian stok berhasil dicatat.');
    }
}
