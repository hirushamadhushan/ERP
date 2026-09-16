<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class SerialReferenceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $rules = ['kind' => ['required', 'in:product,location']];
        if ($this->input('kind') === 'location') {
            $rules += [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:100', 'unique:locations,code'],
            ];
        } else {
            $rules += [
                'name' => ['nullable', 'string', 'max:255'],
                'code' => ['nullable', 'string', 'max:100'],
            ];
        }

        return $rules;
    }
}
