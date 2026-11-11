<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        PurchaseOrder::STATUS_DRAFT,
        PurchaseOrder::STATUS_ORDERED,
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        PurchaseOrder::STATUS_COMPLETED,
        PurchaseOrder::STATUS_CANCELLED,
    ];

    public function index(Request $request): Response
    {
        $status = trim((string) $request->string('status'));
        $supplierId = $request->integer('supplier_id') ?: null;
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));

        if (! in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $purchaseOrders = PurchaseOrder::query()
            ->with('supplier')
            ->withCount('items')
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'filters' => [
                'status' => $status,
                'supplier_id' => $supplierId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function create(PurchaseOrderService $purchaseOrderService): Response
    {
        return Inertia::render('PurchaseOrders/Create', [
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(['id', 'name']),
            'items' => $this->itemsForSelection(),
            'suggestions' => $purchaseOrderService->suggestLines(),
            'purchaseOrder' => null,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, PurchaseOrderService $purchaseOrderService): RedirectResponse
    {
        try {
            $purchaseOrder = $purchaseOrderService->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'lines' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Pesanan pembelian berhasil dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'supplier',
            'items.item',
            'stockDocuments' => fn ($query) => $query->orderByDesc('occurred_on')->orderByDesc('id'),
        ]);

        return Inertia::render('PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder, PurchaseOrderService $purchaseOrderService): Response|RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return to_route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Pesanan hanya dapat diubah saat berstatus draft.');
        }

        $purchaseOrder->load(['items.item', 'supplier']);

        return Inertia::render('PurchaseOrders/Create', [
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(['id', 'name']),
            'items' => $this->itemsForSelection(),
            'suggestions' => $purchaseOrderService->suggestLines(),
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderService $purchaseOrderService,
    ): RedirectResponse {
        try {
            $purchaseOrderService->update($purchaseOrder, $request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'lines' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Pesanan pembelian berhasil diperbarui.');
    }

    public function markOrdered(PurchaseOrder $purchaseOrder, PurchaseOrderService $purchaseOrderService): RedirectResponse
    {
        try {
            $purchaseOrderService->markOrdered($purchaseOrder);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Pesanan pembelian ditandai sudah dipesan.');
    }

    public function cancel(PurchaseOrder $purchaseOrder, PurchaseOrderService $purchaseOrderService): RedirectResponse
    {
        try {
            $purchaseOrderService->cancel($purchaseOrder);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Pesanan pembelian berhasil dibatalkan.');
    }

    public function receiveForm(PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
            return to_route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Penerimaan hanya dapat dicatat untuk pesanan yang sedang dipesan atau diterima sebagian.');
        }

        $purchaseOrder->load(['supplier', 'items.item']);

        return Inertia::render('PurchaseOrders/Receive', [
            'purchaseOrder' => $purchaseOrder,
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function receive(
        ReceivePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderService $purchaseOrderService,
    ): RedirectResponse {
        try {
            $purchaseOrderService->receive($purchaseOrder, $request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'lines' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Penerimaan barang berhasil dicatat.');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function itemsForSelection(): Collection
    {
        return Item::query()
            ->active()
            ->withCurrentStock()
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'unit' => $item->unit,
                'purchase_price' => (float) $item->purchase_price,
                'current_stock' => (float) ($item->current_stock ?? 0),
                'min_stock' => (float) $item->min_stock,
            ]);
    }
}
