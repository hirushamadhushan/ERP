<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreSerialPreviewRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'embedded' => ['nullable', 'boolean'],
        ];
    }
}
