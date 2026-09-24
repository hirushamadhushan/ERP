<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $record = $this->route('record');

        return [
            'category_type' => ['required', Rule::in(['product', 'expense'])],
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($record?->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::notIn([$record?->id]),
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->whereNull('parent_id')
                    ->where('category_type', $this->input('category_type', 'product'))),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['category_type' => $this->input('category_type', 'product')]);
    }
}
