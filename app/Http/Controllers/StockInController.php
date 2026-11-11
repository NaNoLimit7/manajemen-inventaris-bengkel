<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockInRequest;
use App\Models\Item;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockInController extends Controller
{
    public function create(): Response
    {
        $items = Item::query()
            ->active()
            ->withCurrentStock()
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'unit' => $item->unit,
                'has_expiry' => $item->has_expiry,
                'purchase_price' => (float) $item->purchase_price,
                'current_stock' => (float) ($item->current_stock ?? 0),
            ]);

        return Inertia::render('Stock/In', [
            'items' => $items,
            'suppliers' => Supplier::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function store(StoreStockInRequest $request, StockService $stockService): RedirectResponse
    {
        $stockService->receive($request->validated());

        return redirect()
            ->route('stock.in.create')
            ->with('success', 'Barang masuk berhasil dicatat.');
    }
}
