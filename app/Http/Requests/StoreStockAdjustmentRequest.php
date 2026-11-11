<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Models\StockBatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'occurred_on' => ['required', 'date'],
            'notes' => ['required', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('is_active', true),
            ],
            'lines.*.stock_batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'lines.*.counted_quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Validate batch ownership and expiry requirements.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            $itemIds = collect($lines)
                ->pluck('item_id')
                ->filter(fn (mixed $itemId): bool => is_numeric($itemId))
                ->map(fn (mixed $itemId): int => (int) $itemId)
                ->unique()
                ->all();

            $items = Item::query()
                ->whereIn('id', $itemIds)
                ->get()
                ->keyBy('id');

            $batchIds = collect($lines)
                ->pluck('stock_batch_id')
                ->filter(fn (mixed $batchId): bool => is_numeric($batchId))
                ->map(fn (mixed $batchId): int => (int) $batchId)
                ->unique()
                ->all();

            $batches = StockBatch::query()
                ->whereIn('id', $batchIds)
                ->get()
                ->keyBy('id');

            foreach ($lines as $index => $line) {
                $itemId = $line['item_id'] ?? null;

                if (! is_numeric($itemId)) {
                    continue;
                }

                $item = $items->get((int) $itemId);

                if (! $item instanceof Item) {
                    continue;
                }

                $batchId = $line['stock_batch_id'] ?? null;

                if ($item->has_expiry && blank($batchId)) {
                    $validator->errors()->add(
                        "lines.{$index}.stock_batch_id",
                        'Batch stok wajib dipilih untuk barang ini.',
                    );
                }

                if (! is_numeric($batchId)) {
                    continue;
                }

                $batch = $batches->get((int) $batchId);

                if ($batch instanceof StockBatch && $batch->item_id !== $item->id) {
                    $validator->errors()->add(
                        "lines.{$index}.stock_batch_id",
                        'Batch stok tidak sesuai dengan barang yang dipilih.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'occurred_on.required' => 'Tanggal penyesuaian wajib diisi.',
            'occurred_on.date' => 'Tanggal penyesuaian tidak valid.',
            'notes.required' => 'Alasan penyesuaian wajib diisi.',
            'lines.required' => 'Tambahkan minimal satu barang.',
            'lines.min' => 'Tambahkan minimal satu barang.',
            'lines.*.item_id.required' => 'Barang wajib dipilih.',
            'lines.*.item_id.exists' => 'Barang tidak ditemukan atau tidak aktif.',
            'lines.*.stock_batch_id.exists' => 'Batch stok tidak ditemukan.',
            'lines.*.counted_quantity.required' => 'Jumlah hasil hitung wajib diisi.',
            'lines.*.counted_quantity.numeric' => 'Jumlah hasil hitung harus berupa angka.',
            'lines.*.counted_quantity.min' => 'Jumlah hasil hitung tidak boleh negatif.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'occurred_on' => 'tanggal penyesuaian',
            'notes' => 'alasan penyesuaian',
            'lines.*.item_id' => 'barang',
            'lines.*.stock_batch_id' => 'batch stok',
            'lines.*.counted_quantity' => 'jumlah hasil hitung',
        ];
    }
}
