<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class ImportSerialNumbersRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt', 'extensions:xlsx,csv']];
    }
}
