<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreStockOutRequest;
use App\Models\Customer;
use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
                'sale_price' => (float) $item->sale_price,
                'current_stock' => (float) ($item->current_stock ?? 0),
            ]);

        return Inertia::render('Stock/Out', [
            'items' => $items,
            'customers' => Customer::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function store(StoreStockOutRequest $request, StockService $stockService): RedirectResponse
    {
        $data = $request->validated();
        $customerName = trim((string) ($data['customer_name'] ?? ''));
        unset($data['customer_name']);

        try {
            if ($data['purpose'] === 'sold' && $customerName !== '') {
                DB::transaction(function () use ($data, $customerName, $stockService): void {
                    $customer = Customer::query()->create(['name' => $customerName]);

                    $data['customer_id'] = $customer->id;

                    $stockService->issue($data);
                });
            } else {
                $stockService->issue($data);
            }
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
