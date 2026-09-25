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
                    ->where('category_type', $this->input('category_type', 'product'))),
            ],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;
            try {
                \App\Models\Category::validateParent($this->input('parent_id') ? (int) $this->input('parent_id') : null, $this->route('record'));
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $validator->errors()->add('parent_id', $exception->errors()['parent_id'][0]);
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['category_type' => $this->input('category_type', 'product'), 'parent_id' => $this->input('parent_id') ?: null]);
    }
}
