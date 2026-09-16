<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
