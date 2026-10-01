<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ReceiveProductLotRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'location_id' => ['required', 'integer', Rule::exists('location_product', 'location_id')->where('product_id', $product->id)],
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id), Rule::requiredIf($product->product_type === 'variable')],
            'supplier_lot_code' => ['nullable', 'string', 'max:100'],
            'manufactured_at' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_at' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,4'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
