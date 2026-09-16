<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Models\Contact;
use Illuminate\Validation\Rule;

class SaveContactRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $contact = $this->route('contact');
        $rules = [
            'type' => ['required', Rule::in(array_keys(Contact::FORM_TYPES))],
            'contact_id' => ['nullable', 'string', 'max:60', Rule::unique('contacts')->ignore($contact?->id)],
            'entity_type' => ['required', Rule::in(['individual', 'business'])],
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'required_if:entity_type,business', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'pay_term' => ['nullable', 'required_with:pay_term_unit', 'integer', 'min:0', 'max:100000'],
            'pay_term_unit' => ['nullable', 'required_with:pay_term', Rule::in(['days', 'months'])],
            'opening_due_cans' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'commission_percentage' => ['nullable', 'required_if:type,commission', 'numeric', 'between:0,100'],
            'custom_fields' => ['nullable', 'array', 'max:10'],
            'custom_fields.*' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];

        foreach (['customer_group' => 255, 'alternate_number' => 50, 'landline' => 50, 'tax_number' => 100, 'address_line_1' => 255, 'address_line_2' => 255, 'city' => 100, 'state' => 100, 'country' => 100, 'zip_code' => 30] as $field => $length) {
            $rules[$field] = ['nullable', 'string', 'max:'.$length];
        }
        $rules['customer_group'][] = Rule::exists('customer_groups', 'name');

        return $rules;
    }

    public function contactData(): array
    {
        return array_replace([
            'opening_balance' => 0,
            'opening_due_cans' => 0,
            'commission_percentage' => 0,
        ], $this->validated());
    }
}
