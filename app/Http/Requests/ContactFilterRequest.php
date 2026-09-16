<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ContactFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'customer_group' => ['nullable', 'string', 'max:255'],
            'no_sales' => ['nullable', Rule::in(['never', '30', '90', '180', '365'])],
            'due' => ['nullable', 'boolean'],
            'returns' => ['nullable', 'boolean'],
            'advance' => ['nullable', 'boolean'],
            'opening' => ['nullable', 'boolean'],
        ];
    }
}
