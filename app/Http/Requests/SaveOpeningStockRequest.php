<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class SaveOpeningStockRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $variable = $this->route('product')?->product_type === 'variable';
        $leaf = $variable ? '*.*' : '*';

        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => [$variable ? 'array' : 'numeric', 'required', ...($variable ? [] : ['min:0', 'max:999999999', 'decimal:0,4'])],
            'quantities.'.$leaf => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'unit_costs' => ['nullable', 'array'],
            'unit_costs.'.$leaf => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'expiry_dates' => ['nullable', 'array'],
            'expiry_dates.'.$leaf => ['nullable', 'date'],
            'stocked_at' => ['nullable', 'array'],
            'stocked_at.'.$leaf => ['nullable', 'date'],
            'notes' => ['nullable', 'array'],
            'notes.'.$leaf => ['nullable', 'string', 'max:1000'],
        ];
    }
}
