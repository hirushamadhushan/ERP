<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerGroupRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('customer_groups')->ignore($group?->id)],
            'calculation_type' => ['required', Rule::in(['percentage', 'selling_price_group'])],
            'calculation_percentage' => ['exclude_unless:calculation_type,percentage', 'required', 'numeric', 'between:-100,100', 'decimal:0,2'],
            'selling_price_group' => ['exclude_unless:calculation_type,selling_price_group', 'required', 'string', 'max:255'],
        ];
    }

    public function groupData(): array
    {
        return array_replace([
            'calculation_percentage' => 0,
            'selling_price_group' => null,
        ], $this->validated());
    }
}
