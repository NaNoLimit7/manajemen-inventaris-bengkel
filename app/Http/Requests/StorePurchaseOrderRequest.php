<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
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
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('items', 'id')->where('is_active', true),
            ],
            'lines.*.quantity_ordered' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier tidak ditemukan atau tidak aktif.',
            'lines.required' => 'Tambahkan minimal satu barang.',
            'lines.min' => 'Tambahkan minimal satu barang.',
            'lines.*.item_id.required' => 'Barang wajib dipilih.',
            'lines.*.item_id.distinct' => 'Barang yang sama tidak boleh dipilih lebih dari sekali.',
            'lines.*.item_id.exists' => 'Barang tidak ditemukan atau tidak aktif.',
            'lines.*.quantity_ordered.required' => 'Jumlah pesanan wajib diisi.',
            'lines.*.quantity_ordered.numeric' => 'Jumlah pesanan harus berupa angka.',
            'lines.*.quantity_ordered.gt' => 'Jumlah pesanan harus lebih dari 0.',
            'lines.*.unit_price.required' => 'Harga satuan wajib diisi.',
            'lines.*.unit_price.numeric' => 'Harga satuan harus berupa angka.',
            'lines.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'notes' => 'catatan',
            'lines.*.item_id' => 'barang',
            'lines.*.quantity_ordered' => 'jumlah pesanan',
            'lines.*.unit_price' => 'harga satuan',
        ];
    }
}
