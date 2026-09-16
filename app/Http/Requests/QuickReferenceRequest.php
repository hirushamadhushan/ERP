<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class QuickReferenceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $kind = $this->input('kind');
        $table = ['unit' => 'units', 'brand' => 'brands', 'category' => 'categories', 'location' => 'locations'][$kind] ?? 'products';
        $rules = [
            'kind' => ['required', Rule::in(['unit', 'brand', 'category', 'location'])],
            'name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')],
        ];

        if ($kind === 'unit') {
            $rules += [
                'short_name' => ['required', 'string', 'max:30', 'unique:units,short_name'],
                'allow_decimal' => ['required', 'boolean'],
            ];
        }
        if ($kind === 'category') {
            $rules['parent_id'] = ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')];
        }
        if ($kind === 'location') {
            $rules['code'] = ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/D', 'unique:locations,code'];
        }

        return $rules;
    }
}
