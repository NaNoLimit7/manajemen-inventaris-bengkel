<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Item;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class ItemImportService
{
    public function __construct(
        private readonly ItemCodeService $itemCodeService,
        private readonly StockService $stockService,
    ) {}

    /**
     * Parse and validate a CSV file without storing anything.
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     errors: array<int, array{line: int, message: string}>
     * }
     */
    public function parse(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [
                'rows' => [],
                'errors' => [['line' => 0, 'message' => 'File tidak dapat dibaca.']],
            ];
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                return [
                    'rows' => [],
                    'errors' => [['line' => 1, 'message' => 'File CSV kosong.']],
                ];
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);

            $header = fgetcsv($handle, 0, $delimiter);

            if ($header === false || $header === [null]) {
                return [
                    'rows' => [],
                    'errors' => [['line' => 1, 'message' => 'Header CSV tidak valid.']],
                ];
            }

            $headerMap = $this->headerMap($header);
            $requiredColumns = [
                'code',
                'name',
                'category',
                'unit',
                'purchase_price',
                'sale_price',
                'min_stock',
                'has_expiry',
                'initial_stock',
            ];
            $missingColumns = array_values(array_diff($requiredColumns, array_keys($headerMap)));

            if ($missingColumns !== []) {
                return [
                    'rows' => [],
                    'errors' => [[
                        'line' => 1,
                        'message' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missingColumns).'.',
                    ]],
                ];
            }

            $categories = Category::query()->get();
            $categoriesBySlug = $categories->keyBy('slug');
            $categoriesByName = $categories->mapWithKeys(
                fn (Category $category): array => [mb_strtolower($category->name) => $category],
            );

            $existingCodes = [];

            foreach (Item::query()->pluck('code') as $code) {
                $existingCodes[mb_strtolower((string) $code)] = true;
            }

            $seenCodes = [];
            $rows = [];
            $errors = [];
            $lineNumber = 1;

            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $row = $this->rowValues($values, $headerMap);
                $rowErrors = [];

                $code = $row['code'];
                $categoryKey = mb_strtolower($row['category']);
                $category = $categoriesBySlug->get($categoryKey) ?? $categoriesByName->get($categoryKey);
                $hasExpiry = $this->parseBoolean($row['has_expiry']);
                $purchasePrice = $this->parseNonNegativeNumber($row['purchase_price']);
                $salePrice = $this->parseNonNegativeNumber($row['sale_price']);
                $minStock = $this->parseNonNegativeNumber($row['min_stock']);
                $initialStock = $this->parseNonNegativeNumber($row['initial_stock']);
                $expiryDate = $row['expiry_date'];

                if ($row['name'] === '') {
                    $rowErrors[] = 'Nama barang wajib diisi.';
                }

                if ($row['unit'] === '') {
                    $rowErrors[] = 'Satuan wajib diisi.';
                }

                if (! $category instanceof Category) {
                    $rowErrors[] = 'Kategori tidak ditemukan.';
                }

                if ($hasExpiry === null) {
                    $rowErrors[] = 'Nilai has_expiry harus ya/tidak/1/0/true/false.';
                }

                if ($purchasePrice === null) {
                    $rowErrors[] = 'Harga beli harus berupa angka >= 0.';
                }

                if ($salePrice === null) {
                    $rowErrors[] = 'Harga jual harus berupa angka >= 0.';
                }

                if ($minStock === null) {
                    $rowErrors[] = 'Stok minimum harus berupa angka >= 0.';
                }

                if ($initialStock === null) {
                    $rowErrors[] = 'Stok awal harus berupa angka >= 0.';
                }

                if ($code !== '') {
                    $normalizedCode = mb_strtolower($code);

                    if (isset($existingCodes[$normalizedCode])) {
                        $rowErrors[] = "Kode {$code} sudah ada di database.";
                    }

                    if (isset($seenCodes[$normalizedCode])) {
                        $rowErrors[] = "Kode {$code} duplikat di dalam file.";
                    }

                    $seenCodes[$normalizedCode] = true;
                }

                if ($expiryDate !== '' && ! $this->isValidDate($expiryDate)) {
                    $rowErrors[] = 'Format tanggal kedaluwarsa harus YYYY-MM-DD.';
                }

                if ($hasExpiry === true && $initialStock !== null && $initialStock > 0 && $expiryDate === '') {
                    $rowErrors[] = 'Tanggal kedaluwarsa wajib diisi untuk stok awal barang berkedaluwarsa.';
                }

                if ($rowErrors !== []) {
                    foreach ($rowErrors as $message) {
                        $errors[] = ['line' => $lineNumber, 'message' => $message];
                    }

                    continue;
                }

                $rows[] = [
                    'line' => $lineNumber,
                    'code' => $code !== '' ? $code : null,
                    'name' => $row['name'],
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'brand' => $row['brand'] !== '' ? $row['brand'] : null,
                    'unit' => $row['unit'],
                    'purchase_price' => $purchasePrice,
                    'sale_price' => $salePrice,
                    'min_stock' => $minStock,
                    'shelf_location' => $row['shelf_location'] !== '' ? $row['shelf_location'] : null,
                    'has_expiry' => $hasExpiry,
                    'initial_stock' => $initialStock,
                    'expiry_date' => $hasExpiry ? ($expiryDate !== '' ? $expiryDate : null) : null,
                ];
            }

            return ['rows' => $rows, 'errors' => $errors];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Store validated rows and create one initial stock document.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function import(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            $stockLines = [];

            foreach ($rows as $row) {
                $item = $this->itemCodeService->create([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'category_id' => $row['category_id'],
                    'brand' => $row['brand'],
                    'unit' => $row['unit'],
                    'purchase_price' => $row['purchase_price'],
                    'sale_price' => $row['sale_price'],
                    'min_stock' => $row['min_stock'],
                    'shelf_location' => $row['shelf_location'],
                    'has_expiry' => $row['has_expiry'],
                    'is_active' => true,
                ]);

                if ((float) $row['initial_stock'] > 0) {
                    $stockLines[] = [
                        'item_id' => $item->id,
                        'quantity' => $row['initial_stock'],
                        'unit_cost' => $row['purchase_price'],
                        'expiry_date' => $row['has_expiry'] ? $row['expiry_date'] : null,
                    ];
                }
            }

            if ($stockLines !== []) {
                $this->stockService->receive([
                    'occurred_on' => now()->toDateString(),
                    'notes' => 'Stok awal (import)',
                    'lines' => $stockLines,
                ]);
            }
        });
    }

    /**
     * @param  array<int, mixed>  $header
     * @return array<string, int>
     */
    private function headerMap(array $header): array
    {
        $map = [];

        foreach ($header as $index => $column) {
            $column = trim((string) $column);

            if ($index === 0) {
                $column = preg_replace('/^\xEF\xBB\xBF/', '', $column) ?? $column;
            }

            $map[mb_strtolower($column)] = $index;
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<string, int>  $headerMap
     * @return array<string, string>
     */
    private function rowValues(array $values, array $headerMap): array
    {
        $row = [
            'code' => '',
            'name' => '',
            'category' => '',
            'brand' => '',
            'unit' => '',
            'purchase_price' => '',
            'sale_price' => '',
            'min_stock' => '',
            'shelf_location' => '',
            'has_expiry' => '',
            'initial_stock' => '',
            'expiry_date' => '',
        ];

        foreach ($headerMap as $column => $index) {
            $row[$column] = trim((string) ($values[$index] ?? ''));
        }

        return $row;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseBoolean(string $value): ?bool
    {
        return match (mb_strtolower(trim($value))) {
            'ya', '1', 'true' => true,
            'tidak', '0', 'false' => false,
            default => null,
        };
    }

    private function parseNonNegativeNumber(string $value): ?float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number >= 0 ? $number : null;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
