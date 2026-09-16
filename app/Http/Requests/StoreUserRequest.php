<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:4'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'status' => ['required', Rule::in(['active', 'offline', 'suspended'])],
        ];
    }

    public function messages(): array
    {
        return ['email.lowercase' => 'Email address must contain lowercase letters only.'];
    }
}
