<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Category;
use App\Models\Item;
use App\Services\ItemCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $categoryId = $request->integer('category_id') ?: null;

        $items = Item::query()
            ->with('category')
            ->withCurrentStock()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Items/Index', [
            'items' => $items,
            'categories' => $this->categories(),
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Items/Create', [
            'categories' => $this->categories(),
        ]);
    }

    public function store(StoreItemRequest $request, ItemCodeService $itemCodeService): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $item = $itemCodeService->create($data);

        return redirect()
            ->route('items.show', $item)
            ->with('success', 'Barang berhasil ditambahkan.');
    }

    public function show(Item $item): Response
    {
        $item->load([
            'category',
            'batches' => fn ($query) => $query->orderBy('expiry_date'),
            'stockMovements' => fn ($query) => $query
                ->with(['stockDocument', 'stockBatch'])
                ->latest('id')
                ->limit(20),
        ]);

        $item->setAttribute(
            'current_stock',
            $item->batches->sum(fn ($batch): float => (float) $batch->quantity_remaining),
        );

        return Inertia::render('Items/Show', [
            'item' => $item,
        ]);
    }

    public function edit(Item $item): Response
    {
        return Inertia::render('Items/Edit', [
            'item' => $item->load('category'),
            'categories' => $this->categories(),
            'hasStockHistory' => $item->stockMovements()->exists(),
        ]);
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['code'] ?? null)) {
            $data['code'] = $item->code;
        }

        $item->update($data);

        return redirect()
            ->route('items.show', $item)
            ->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        if ($item->stockMovements()->exists() || $item->batches()->exists()) {
            return redirect()
                ->route('items.index')
                ->with('error', 'Barang yang sudah memiliki riwayat stok tidak dapat dihapus. Nonaktifkan barang ini.');
        }

        $item->delete();

        return redirect()
            ->route('items.index')
            ->with('success', 'Barang berhasil dihapus.');
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);
    }
}
