<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ProductFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'product_type' => ['nullable', Rule::in(['single', 'variable', 'combo'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'stock_status' => ['nullable', Rule::in(['managed', 'unmanaged', 'serial'])],
            'not_for_selling' => ['nullable', 'boolean'],
        ];
    }
}
