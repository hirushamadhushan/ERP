<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveBrandRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // Keep the original unique name reserved after a soft delete. This
            // avoids creating two records that represent the same brand.
            'name' => ['required', 'string', 'max:100', Rule::unique('brands')->ignore($this->route('record')?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'use_for_repair' => ['sometimes', 'boolean'],
        ];
    }
}
