<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveUnitRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('units')->ignore($unit?->id)],
            'short_name' => ['required', 'string', 'max:30', Rule::unique('units')->ignore($unit?->id)],
            'allow_decimal' => ['required', 'boolean'],
            'is_multiple' => ['sometimes', 'boolean'],
            'base_unit_id' => ['exclude_unless:is_multiple,1', 'required', 'integer', Rule::exists('units', 'id')],
            'base_unit_multiplier' => ['exclude_unless:is_multiple,1', 'required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,6'],
        ];
    }

    public function unitData(): array
    {
        $data = array_replace(['base_unit_id' => null, 'base_unit_multiplier' => null], $this->validated());
        unset($data['is_multiple']);

        return $data;
    }
}
