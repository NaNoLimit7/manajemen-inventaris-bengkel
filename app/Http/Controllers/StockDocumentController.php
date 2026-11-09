<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));
        $itemId = $request->integer('item_id') ?: null;

        $documents = StockDocument::query()
            ->withCount('stockMovements')
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('occurred_on', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('occurred_on', '<=', $dateTo))
            ->when($itemId, fn ($query) => $query->whereHas(
                'stockMovements',
                fn ($query) => $query->where('item_id', $itemId),
            ))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Stock/Documents', [
            'documents' => $documents,
            'items' => Item::query()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'item_id' => $itemId,
            ],
        ]);
    }

    public function show(StockDocument $stockDocument): Response
    {
        $stockDocument->load([
            'stockMovements' => fn ($query) => $query
                ->with(['item', 'stockBatch'])
                ->orderBy('id'),
        ]);

        return Inertia::render('Stock/Document', [
            'document' => $stockDocument,
        ]);
    }
}
