<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Validation\Rule;

class StoreUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'prefix' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'],
            'allow_login' => ['required', 'boolean'],
            'username' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'password' => ['nullable', Rule::requiredIf(fn () => $this->boolean('allow_login')), 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            // Kept for older API clients; the form itself sends the normalized role_id.
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'status' => ['required', Rule::in(['active', 'offline', 'suspended'])],
            'all_locations' => ['required', 'boolean'],
            'location_ids' => ['nullable', Rule::requiredIf(fn () => ! $this->boolean('all_locations')), 'array', 'min:1'],
            'location_ids.*' => ['integer', 'distinct', Rule::exists('locations', 'id')],
            'commission_percent' => ['required', 'numeric', 'between:0,100'],
            'max_sales_discount_percent' => ['required', 'numeric', 'between:0,100'],
            'restrict_contacts' => ['required', 'boolean'],
            'contact_ids' => ['nullable', Rule::requiredIf(fn () => $this->boolean('restrict_contacts')), 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'distinct', Rule::exists('contacts', 'id')->where('status', 'active')],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'alternate_contact' => ['nullable', 'string', 'max:40'],
            'family_contact' => ['nullable', 'string', 'max:40'],
            'facebook_link' => ['nullable', 'url', 'max:255'],
            'twitter_link' => ['nullable', 'url', 'max:255'],
            'social_media_1' => ['nullable', 'url', 'max:255'],
            'social_media_2' => ['nullable', 'url', 'max:255'],
            'custom_field_1' => ['nullable', 'string', 'max:255'],
            'custom_field_2' => ['nullable', 'string', 'max:255'],
            'custom_field_3' => ['nullable', 'string', 'max:255'],
            'custom_field_4' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'id_proof_name' => ['nullable', 'string', 'max:100'],
            'id_proof_number' => ['nullable', 'string', 'max:100'],
            'permanent_address' => ['nullable', 'string', 'max:2000'],
            'current_address' => ['nullable', 'string', 'max:2000'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_identifier_code' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'tax_payer_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $legacyRole = $this->input('role');
        $legacyName = trim((string) $this->input('name'));
        $this->merge([
            'first_name' => $this->input('first_name', $legacyName),
            'role_id' => $this->input('role_id', $legacyRole ? Role::where('name', $legacyRole)->value('id') : null),
            'allow_login' => $this->boolean('allow_login', true),
            'all_locations' => $this->boolean('all_locations', true),
            'restrict_contacts' => $this->boolean('restrict_contacts'),
            // Empty optional limits are stored as zero while the form stays uncluttered.
            'commission_percent' => $this->filled('commission_percent') ? $this->input('commission_percent') : 0,
            'max_sales_discount_percent' => $this->filled('max_sales_discount_percent') ? $this->input('max_sales_discount_percent') : 0,
        ]);
    }

    public function messages(): array
    {
        return ['email.lowercase' => 'Email address must contain lowercase letters only.'];
    }
}
