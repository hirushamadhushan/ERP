<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class DeleteSerialNumbersRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
