<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($this->route('id'))],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array', 'max:200'],
            'permissions.*' => ['string', 'max:100'],
        ];
    }

    public function roleData(): array
    {
        return array_replace($this->validated(), ['permissions' => $this->input('permissions', [])]);
    }
}
