<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockInRequest extends FormRequest
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
            'reference_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where('is_active', true),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Validate expiry requirements against the selected item.
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
                ->all();

            $items = Item::query()
                ->whereIn('id', $itemIds)
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

                $expiryDate = $line['expiry_date'] ?? null;

                if ($item->has_expiry && blank($expiryDate)) {
                    $validator->errors()->add(
                        "lines.{$index}.expiry_date",
                        'Tanggal kedaluwarsa wajib diisi untuk barang ini.',
                    );
                }

                if (! $item->has_expiry && filled($expiryDate)) {
                    $validator->errors()->add(
                        "lines.{$index}.expiry_date",
                        'Barang ini tidak menggunakan tanggal kedaluwarsa.',
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
            'occurred_on.required' => 'Tanggal transaksi wajib diisi.',
            'occurred_on.date' => 'Tanggal transaksi tidak valid.',
            'lines.required' => 'Tambahkan minimal satu barang.',
            'lines.min' => 'Tambahkan minimal satu barang.',
            'lines.*.item_id.required' => 'Barang wajib dipilih.',
            'lines.*.item_id.exists' => 'Barang tidak ditemukan atau tidak aktif.',
            'lines.*.quantity.required' => 'Jumlah wajib diisi.',
            'lines.*.quantity.numeric' => 'Jumlah harus berupa angka.',
            'lines.*.quantity.gt' => 'Jumlah harus lebih dari 0.',
            'lines.*.unit_cost.required' => 'Harga beli wajib diisi.',
            'lines.*.unit_cost.numeric' => 'Harga beli harus berupa angka.',
            'lines.*.unit_cost.min' => 'Harga beli tidak boleh negatif.',
            'lines.*.expiry_date.date_format' => 'Format tanggal kedaluwarsa tidak valid.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'occurred_on' => 'tanggal transaksi',
            'reference_number' => 'nomor faktur',
            'notes' => 'catatan',
            'lines.*.item_id' => 'barang',
            'lines.*.quantity' => 'jumlah',
            'lines.*.unit_cost' => 'harga beli',
            'lines.*.expiry_date' => 'tanggal kedaluwarsa',
        ];
    }
}
