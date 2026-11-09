<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateItemRequest extends FormRequest
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
        $item = $this->route('item');
        $itemId = $item instanceof Item ? $item->getKey() : $item;

        return [
            'code' => ['nullable', 'string', 'max:40', Rule::unique('items', 'code')->ignore($itemId)],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:20'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'shelf_location' => ['nullable', 'string', 'max:50'],
            'has_expiry' => ['required', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Validate rules that require the current item state.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $item = $this->route('item');

            if (! $item instanceof Item || ! $this->has('has_expiry')) {
                return;
            }

            $newValue = filter_var(
                $this->input('has_expiry'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );

            if ($newValue === null) {
                return;
            }

            if ($newValue !== $item->has_expiry && $item->stockMovements()->exists()) {
                $validator->errors()->add(
                    'has_expiry',
                    'Penanda kedaluwarsa tidak dapat diubah karena barang sudah memiliki riwayat stok.',
                );
            }
        });
    }
}
