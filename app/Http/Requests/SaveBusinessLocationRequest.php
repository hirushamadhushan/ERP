<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/D', Rule::unique('locations', 'code')->ignore($this->route('location')?->id)],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:30'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'price_group' => ['nullable', 'string', 'max:255'],
            'invoice_scheme_id' => ['required', 'integer', 'exists:invoice_schemes,id'],
            'invoice_layout_pos_id' => ['required', 'integer', 'exists:invoice_layouts,id'],
            'invoice_layout_sale_id' => ['required', 'integer', 'exists:invoice_layouts,id'],
            'contacts' => ['nullable', 'array'],
            'contacts.mobile' => ['nullable', 'string', 'max:50'],
            'contacts.alternate_phone' => ['nullable', 'string', 'max:50'],
            'contacts.email' => ['nullable', 'email', 'max:255'],
            'contacts.website' => ['nullable', 'url', 'max:255'],
            'custom_fields' => ['nullable', 'array', 'max:4'],
            'custom_fields.*' => ['nullable', 'string', 'max:255'],
            'featured_product_ids' => ['nullable', 'array', 'max:100'],
            'featured_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*.enabled' => ['nullable', 'boolean'],
            'payment_methods.*.account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
        ];
    }
}
