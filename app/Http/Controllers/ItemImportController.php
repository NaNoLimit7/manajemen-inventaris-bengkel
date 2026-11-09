<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportItemsRequest;
use App\Services\ItemImportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Items/Import', [
            'preview' => null,
            'rowErrors' => [],
        ]);
    }

    public function preview(ImportItemsRequest $request, ItemImportService $importService): Response
    {
        $result = $importService->parse($request->file('file')->getRealPath());

        return $this->previewResponse($result);
    }

    public function store(ImportItemsRequest $request, ItemImportService $importService): RedirectResponse|Response
    {
        $result = $importService->parse($request->file('file')->getRealPath());

        if ($result['errors'] !== []) {
            return $this->previewResponse($result);
        }

        $importService->import($result['rows']);

        return redirect()
            ->route('items.index')
            ->with('success', 'Import selesai: '.count($result['rows']).' barang.');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'code',
                'name',
                'category',
                'brand',
                'unit',
                'purchase_price',
                'sale_price',
                'min_stock',
                'shelf_location',
                'has_expiry',
                'initial_stock',
                'expiry_date',
            ]);
            fputcsv($handle, [
                '',
                'Contoh Oli Mesin',
                'consumable',
                'Yamaha',
                'botol',
                '45000',
                '55000',
                '6',
                'RAK-01',
                'ya',
                '10',
                '2027-01-01',
            ]);

            fclose($handle);
        }, 'template-import-barang.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{
     *     rows: array<int, array<string, mixed>>,
     *     errors: array<int, array{line: int, message: string}>
     * }  $result
     */
    private function previewResponse(array $result): Response
    {
        if ($result['errors'] !== []) {
            return Inertia::render('Items/Import', [
                'preview' => null,
                'rowErrors' => $result['errors'],
            ]);
        }

        $rows = $result['rows'];
        $summary = [
            'total_items' => count($rows),
            'initial_stock_items' => count(array_filter(
                $rows,
                fn (array $row): bool => (float) $row['initial_stock'] > 0,
            )),
            'total_initial_stock' => array_sum(array_map(
                fn (array $row): float => (float) $row['initial_stock'],
                $rows,
            )),
        ];

        return Inertia::render('Items/Import', [
            'preview' => [
                'rows' => array_slice($rows, 0, 20),
                'summary' => $summary,
                'truncated' => count($rows) > 20,
            ],
            'rowErrors' => [],
        ]);
    }
}
