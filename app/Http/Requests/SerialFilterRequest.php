<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class SerialFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable', 'in:available,sold'],
        ];
    }
}
