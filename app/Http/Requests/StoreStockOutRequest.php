<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockOutRequest extends FormRequest
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
            'purpose' => ['required', Rule::in(['sold', 'service', 'workshop_use', 'damaged_lost'])],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('items', 'id')->where('is_active', true),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Validate available stock before the service locks the rows.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $purpose = (string) $this->input('purpose');
            $customerName = trim((string) $this->input('customer_name', ''));
            $customerId = $this->input('customer_id');

            if ($purpose !== 'sold') {
                if ($customerName !== '' || filled($customerId)) {
                    $validator->errors()->add(
                        'customer_id',
                        'Pelanggan hanya dapat diisi untuk tujuan penjualan.',
                    );
                }
            } else {
                if ($customerName !== '' && filled($customerId)) {
                    $validator->errors()->add(
                        'customer_name',
                        'Pilih pelanggan yang ada atau isi nama pelanggan baru, bukan keduanya.',
                    );
                }

                $lineInputs = $this->input('lines', []);

                if (is_array($lineInputs)) {
                    foreach ($lineInputs as $index => $line) {
                        if (! isset($line['unit_price']) || $line['unit_price'] === '' || $line['unit_price'] === null) {
                            $validator->errors()->add(
                                "lines.{$index}.unit_price",
                                'Harga jual wajib diisi untuk barang yang dijual.',
                            );
                        }
                    }
                }
            }

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
                ->withCurrentStock()
                ->whereIn('id', $itemIds)
                ->get()
                ->keyBy('id');

            foreach ($lines as $index => $line) {
                $itemId = $line['item_id'] ?? null;
                $quantity = $line['quantity'] ?? null;

                if (! is_numeric($itemId) || ! is_numeric($quantity)) {
                    continue;
                }

                $item = $items->get((int) $itemId);

                if (! $item instanceof Item) {
                    continue;
                }

                $available = (string) ($item->current_stock ?? '0');
                $requested = bcadd((string) $quantity, '0', 2);

                if (bccomp($available, $requested, 2) < 0) {
                    $validator->errors()->add(
                        "lines.{$index}.quantity",
                        "Stok {$item->name} tidak cukup. Tersisa {$available} {$item->unit}.",
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
            'purpose.required' => 'Tujuan barang keluar wajib dipilih.',
            'purpose.in' => 'Tujuan barang keluar tidak valid.',
            'customer_id.exists' => 'Pelanggan tidak ditemukan atau tidak aktif.',
            'customer_name.max' => 'Nama pelanggan baru maksimal 120 karakter.',
            'lines.required' => 'Tambahkan minimal satu barang.',
            'lines.min' => 'Tambahkan minimal satu barang.',
            'lines.*.item_id.required' => 'Barang wajib dipilih.',
            'lines.*.item_id.exists' => 'Barang tidak ditemukan atau tidak aktif.',
            'lines.*.item_id.distinct' => 'Barang yang sama tidak boleh dipilih lebih dari sekali.',
            'lines.*.quantity.required' => 'Jumlah wajib diisi.',
            'lines.*.quantity.numeric' => 'Jumlah harus berupa angka.',
            'lines.*.quantity.gt' => 'Jumlah harus lebih dari 0.',
            'lines.*.unit_price.numeric' => 'Harga jual harus berupa angka.',
            'lines.*.unit_price.min' => 'Harga jual tidak boleh negatif.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'occurred_on' => 'tanggal transaksi',
            'purpose' => 'tujuan barang keluar',
            'customer_id' => 'pelanggan',
            'customer_name' => 'nama pelanggan baru',
            'notes' => 'catatan',
            'lines.*.item_id' => 'barang',
            'lines.*.quantity' => 'jumlah',
            'lines.*.unit_price' => 'harga jual',
        ];
    }
}
