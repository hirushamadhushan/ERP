<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends StoreUserRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $userId = $this->route('user')?->id;

        $rules['email'] = ['required', 'email', 'lowercase', 'max:255', Rule::unique('users', 'email')->ignore($userId)];
        $rules['username'] = ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($userId)];
        $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];

        return $rules;
    }
}
