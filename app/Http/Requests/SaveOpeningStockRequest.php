<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class SaveOpeningStockRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
        ];
    }
}
