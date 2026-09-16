<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class ImportContactsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt', 'extensions:csv']];
    }
}
