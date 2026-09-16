<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveBrandRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('brands')->ignore($this->route('record')?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
