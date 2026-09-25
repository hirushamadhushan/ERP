<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveTaxRateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('tax_rates', 'name')->ignore($this->route('taxRate')?->id)],
            'amount' => ['required', 'numeric', 'between:0,100', 'decimal:0,3'],
            'for_tax_group' => ['required', 'boolean'],
        ];
    }
}
