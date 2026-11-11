<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\StockDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));

        $customers = Customer::query()
            ->withCount('stockDocuments')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::query()->create($request->validated());

        return redirect()
            ->route('customers.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function show(Customer $customer): Response
    {
        $documents = StockDocument::query()
            ->where('type', 'out')
            ->where('purpose', 'sold')
            ->where('customer_id', $customer->id)
            ->with([
                'stockMovements.item',
                'reversedBy',
            ])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $history = $documents->map(function (StockDocument $document): array {
            $isReversed = $document->reversedBy !== null;

            $items = $document->stockMovements
                ->groupBy('item_id')
                ->map(function ($movements) use ($isReversed): array {
                    $first = $movements->first();
                    $quantity = $movements->sum(fn ($movement): float => (float) $movement->quantity);
                    $unitPrice = (float) ($first->unit_price ?? 0);
                    $value = $isReversed ? 0.0 : abs($quantity) * $unitPrice;

                    return [
                        'item_name' => $first->item?->name ?? 'Barang tidak ditemukan',
                        'item_code' => $first->item?->code ?? '-',
                        'unit' => $first->item?->unit ?? '',
                        'quantity' => abs($quantity),
                        'unit_price' => $unitPrice,
                        'value' => $value,
                    ];
                })
                ->values();

            return [
                'id' => $document->id,
                'occurred_on' => $document->occurred_on?->toDateString(),
                'notes' => $document->notes,
                'is_cancelled' => $isReversed,
                'items' => $items,
                'total' => $items->sum('value'),
            ];
        });

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'history' => $history->values(),
            'totalPurchases' => $history->sum('total'),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()
            ->route('customers.index')
            ->with('success', 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->stockDocuments()->exists()) {
            return redirect()
                ->route('customers.index')
                ->with('error', 'Pelanggan yang sudah memiliki riwayat penjualan tidak dapat dihapus. Nonaktifkan pelanggan ini.');
        }

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Pelanggan berhasil dihapus.');
    }
}
