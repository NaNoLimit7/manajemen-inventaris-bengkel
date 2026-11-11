<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReceivePurchaseOrderRequest extends FormRequest
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
            'lines.*.purchase_order_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('purchase_order_items', 'id'),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Validate quantities against the purchase order and expiry rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $purchaseOrder = $this->route('purchaseOrder');

            if (! $purchaseOrder instanceof PurchaseOrder) {
                return;
            }

            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            $poItems = $purchaseOrder->items()->with('item')->get()->keyBy('id');
            $hasPositiveQuantity = false;

            foreach ($lines as $index => $line) {
                $poItem = $poItems->get((int) ($line['purchase_order_item_id'] ?? 0));

                if (! $poItem instanceof PurchaseOrderItem) {
                    $validator->errors()->add(
                        "lines.{$index}.purchase_order_item_id",
                        'Barang tidak ditemukan pada pesanan ini.',
                    );

                    continue;
                }

                $quantity = $line['quantity'] ?? null;
                $isPositive = false;

                if (is_numeric($quantity)) {
                    $requested = bcadd((string) $quantity, '0', 2);
                    $isPositive = bccomp($requested, '0', 2) > 0;
                    $remaining = bcsub(
                        (string) $poItem->quantity_ordered,
                        (string) $poItem->quantity_received,
                        2,
                    );

                    if ($isPositive) {
                        $hasPositiveQuantity = true;
                    }

                    if (bccomp($requested, $remaining, 2) > 0) {
                        $validator->errors()->add(
                            "lines.{$index}.quantity",
                            sprintf(
                                'Jumlah diterima melebihi sisa pesanan. Sisa %s %s.',
                                $remaining,
                                $poItem->item?->unit ?? '',
                            ),
                        );
                    }
                }

                if (! $isPositive) {
                    continue;
                }

                $expiryDate = $line['expiry_date'] ?? null;

                if ($poItem->item?->has_expiry && blank($expiryDate)) {
                    $validator->errors()->add(
                        "lines.{$index}.expiry_date",
                        'Tanggal kedaluwarsa wajib diisi untuk barang ini.',
                    );
                }

                if ($poItem->item && ! $poItem->item->has_expiry && filled($expiryDate)) {
                    $validator->errors()->add(
                        "lines.{$index}.expiry_date",
                        'Barang ini tidak menggunakan tanggal kedaluwarsa.',
                    );
                }
            }

            if (! $hasPositiveQuantity) {
                $validator->errors()->add(
                    'lines',
                    'Isi minimal satu jumlah diterima lebih dari 0.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'occurred_on.required' => 'Tanggal penerimaan wajib diisi.',
            'occurred_on.date' => 'Tanggal penerimaan tidak valid.',
            'lines.required' => 'Tambahkan minimal satu barang yang diterima.',
            'lines.min' => 'Tambahkan minimal satu barang yang diterima.',
            'lines.*.purchase_order_item_id.required' => 'Barang pesanan wajib dipilih.',
            'lines.*.purchase_order_item_id.exists' => 'Barang pesanan tidak ditemukan.',
            'lines.*.purchase_order_item_id.distinct' => 'Barang yang sama tidak boleh dipilih lebih dari sekali.',
            'lines.*.quantity.required' => 'Jumlah diterima wajib diisi.',
            'lines.*.quantity.numeric' => 'Jumlah diterima harus berupa angka.',
            'lines.*.quantity.min' => 'Jumlah diterima tidak boleh negatif.',
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
            'occurred_on' => 'tanggal penerimaan',
            'reference_number' => 'nomor faktur',
            'notes' => 'catatan',
            'lines.*.purchase_order_item_id' => 'barang pesanan',
            'lines.*.quantity' => 'jumlah diterima',
            'lines.*.unit_cost' => 'harga beli',
            'lines.*.expiry_date' => 'tanggal kedaluwarsa',
        ];
    }
}
