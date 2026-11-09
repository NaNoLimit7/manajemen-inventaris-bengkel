<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreStockOutRequest;
use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockOutController extends Controller
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
                'current_stock' => (float) ($item->current_stock ?? 0),
            ]);

        return Inertia::render('Stock/Out', [
            'items' => $items,
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function store(StoreStockOutRequest $request, StockService $stockService): RedirectResponse
    {
        try {
            $stockService->issue($request->validated());
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages([
                'lines' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('stock.out.create')
            ->with('success', 'Barang keluar berhasil dicatat.');
    }
}
