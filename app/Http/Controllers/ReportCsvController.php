<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\AlertService;
use App\Services\ReportService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsvController extends Controller
{
    private const TYPE_LABELS = [
        'in' => 'Masuk',
        'out' => 'Keluar',
        'adjustment' => 'Penyesuaian',
        'reversal' => 'Koreksi',
    ];

    public function stock(Request $request, ReportService $reportService): StreamedResponse
    {
        $items = $reportService->stockReport([
            'search' => trim((string) $request->string('search')),
            'category_id' => $request->integer('category_id') ?: null,
            'is_active' => $request->has('is_active') ? $request->string('is_active')->toString() : null,
        ]);

        return $this->download(
            'laporan-stok-'.now()->toDateString().'.csv',
            ['Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Stok', 'Stok Minimum', 'Harga Beli Terakhir', 'Nilai Persediaan', 'Status'],
            function ($handle) use ($items): void {
                foreach ($items as $item) {
                    $this->writeRow($handle, [
                        $item->code,
                        $item->name,
                        $item->category?->name ?? '-',
                        $item->unit,
                        (string) $item->current_stock,
                        (string) $item->min_stock,
                        (string) $item->purchase_price,
                        (string) $item->inventory_value,
                        $item->is_active ? 'Aktif' : 'Nonaktif',
                    ]);
                }
            },
        );
    }

    public function stockCard(Request $request, Item $item, ReportService $reportService): StreamedResponse
    {
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));
        $type = trim((string) $request->string('type'));

        $movements = $reportService->stockCard($item, [
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'type' => $type !== '' ? $type : null,
        ]);

        return $this->download(
            'kartu-stok-'.$item->code.'-'.now()->toDateString().'.csv',
            ['Tanggal', 'Jenis', 'Referensi', 'Catatan', 'Masuk', 'Keluar', 'Saldo', 'Harga'],
            function ($handle) use ($movements): void {
                foreach ($movements as $movement) {
                    $quantity = (float) $movement->quantity;
                    $document = $movement->stockDocument;
                    $price = $movement->unit_price ?? $movement->unit_cost;

                    $this->writeRow($handle, [
                        $document?->occurred_on?->toDateString() ?? '-',
                        self::TYPE_LABELS[$document?->type] ?? $document?->type ?? '-',
                        $document?->reference_number ?? '-',
                        $document?->notes ?? '-',
                        $quantity > 0 ? (string) $movement->quantity : '',
                        $quantity < 0 ? ltrim((string) $movement->quantity, '-') : '',
                        (string) $movement->running_balance,
                        $price !== null ? (string) $price : '',
                    ]);
                }
            },
        );
    }

    public function sales(Request $request, ReportService $reportService): StreamedResponse
    {
        $dateFrom = trim((string) $request->string('date_from'));
        $dateTo = trim((string) $request->string('date_to'));
        $groupBy = $request->string('group_by')->toString() === 'month' ? 'month' : 'day';

        $report = $reportService->salesReport([
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'group_by' => $groupBy,
        ]);

        return $this->download(
            'laporan-penjualan-'.now()->toDateString().'.csv',
            ['Periode', 'Jumlah Transaksi', 'Nilai Penjualan'],
            function ($handle) use ($report): void {
                $this->writeRow($handle, ['Laporan Penjualan']);
                $this->writeRow($handle, ['Periode', $report['date_from'].' s.d. '.$report['date_to']]);
                $this->writeRow($handle, ['Total Penjualan', (string) $report['total_sales']]);
                $this->writeRow($handle, ['Jumlah Transaksi', (string) $report['transaction_count']]);
                $this->writeRow($handle, []);

                $this->writeRow($handle, ['Penjualan per '.($report['group_by'] === 'month' ? 'Bulan' : 'Hari')]);
                $this->writeRow($handle, ['Periode', 'Jumlah Transaksi', 'Nilai Penjualan']);

                foreach ($report['series'] as $row) {
                    $this->writeRow($handle, [$row['period'], (string) $row['transaction_count'], (string) $row['total']]);
                }

                $this->writeRow($handle, []);
                $this->writeRow($handle, ['Penjualan per Barang']);
                $this->writeRow($handle, ['Barang', 'Kode', 'Satuan', 'Jumlah Terjual', 'Nilai']);

                foreach ($report['items'] as $row) {
                    $this->writeRow($handle, [
                        $row['item_name'],
                        $row['item_code'],
                        $row['unit'],
                        (string) $row['quantity'],
                        (string) $row['total'],
                    ]);
                }

                $this->writeRow($handle, []);
                $this->writeRow($handle, ['Pembelian per Pelanggan']);
                $this->writeRow($handle, ['Pelanggan', 'Jumlah Transaksi', 'Total Pembelian']);

                foreach ($report['customers'] as $row) {
                    $this->writeRow($handle, [
                        $row['customer_name'],
                        (string) $row['transaction_count'],
                        (string) $row['total'],
                    ]);
                }
            },
        );
    }

    public function alerts(AlertService $alertService): StreamedResponse
    {
        $lowStockItems = $alertService->lowStockItems();
        $expiringBatches = $alertService->expiringBatches();
        $expiredBatches = $alertService->expiredBatches();

        return $this->download(
            'laporan-peringatan-'.now()->toDateString().'.csv',
            ['Kategori', 'Barang', 'Kode', 'Kedaluwarsa', 'Sisa Stok', 'Stok Minimum', 'Satuan'],
            function ($handle) use ($lowStockItems, $expiringBatches, $expiredBatches): void {
                $this->writeRow($handle, ['Stok Menipis']);
                $this->writeRow($handle, ['Barang', 'Kode', 'Satuan', 'Stok Saat Ini', 'Stok Minimum']);

                foreach ($lowStockItems as $item) {
                    $this->writeRow($handle, [
                        $item->name,
                        $item->code,
                        $item->unit,
                        (string) $item->current_stock,
                        (string) $item->min_stock,
                    ]);
                }

                $this->writeRow($handle, []);
                $this->writeRow($handle, ['Batch Mendekati Kedaluwarsa']);
                $this->writeRow($handle, ['Barang', 'Kode', 'Kedaluwarsa', 'Sisa Stok', 'Satuan']);

                foreach ($expiringBatches as $batch) {
                    $this->writeRow($handle, [
                        $batch->item?->name ?? '-',
                        $batch->item?->code ?? '-',
                        $batch->expiry_date?->toDateString() ?? '-',
                        (string) $batch->quantity_remaining,
                        $batch->item?->unit ?? '',
                    ]);
                }

                $this->writeRow($handle, []);
                $this->writeRow($handle, ['Batch Sudah Kedaluwarsa']);
                $this->writeRow($handle, ['Barang', 'Kode', 'Kedaluwarsa', 'Sisa Stok', 'Satuan']);

                foreach ($expiredBatches as $batch) {
                    $this->writeRow($handle, [
                        $batch->item?->name ?? '-',
                        $batch->item?->code ?? '-',
                        $batch->expiry_date?->toDateString() ?? '-',
                        (string) $batch->quantity_remaining,
                        $batch->item?->unit ?? '',
                    ]);
                }
            },
        );
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function download(string $filename, array $headers, Closure $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $writer): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");
            $this->writeRow($handle, $headers);
            $writer($handle);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    private function writeRow(mixed $handle, array $row): void
    {
        fputcsv($handle, array_map(
            fn (mixed $value): string => $this->escapeFormula((string) $value),
            $row,
        ));
    }

    private function escapeFormula(string $value): string
    {
        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
