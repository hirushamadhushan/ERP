<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveTaxGroupRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('tax_rates', 'name')->ignore($this->route('taxGroup')?->id)],
            'tax_rate_ids' => ['required', 'array', 'min:1'],
            'tax_rate_ids.*' => ['required', 'integer', 'distinct', Rule::exists('tax_rates', 'id')->where('is_tax_group', 0)],
        ];
    }
}
