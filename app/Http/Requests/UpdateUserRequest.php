<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($userId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users')->ignore($userId)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'status' => ['required', Rule::in(['active', 'offline', 'suspended'])],
            'password' => ['nullable', 'string', 'min:4'],
        ];
    }

    public function messages(): array
    {
        return ['email.lowercase' => 'Email address must contain lowercase letters only.'];
    }
}
