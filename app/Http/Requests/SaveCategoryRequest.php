<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($this->route('record')?->id)],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
