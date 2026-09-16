<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveWarrantyRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('warranties')->ignore($this->route('warranty')?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration' => ['required', 'integer', 'min:1', 'max:9999'],
            'duration_type' => ['required', 'string', Rule::in(['days', 'months', 'years'])],
        ];
    }
}
