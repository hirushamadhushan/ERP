<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\ValidationException;

class GenerateSerialNumbersRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'prefix' => ['nullable', 'string', 'max:20', 'regex:/^[!-~]*$/D'],
            'middle_fix' => ['nullable', 'string', 'max:20', 'regex:/^[!-~]*$/D'],
            'post_fix' => ['nullable', 'string', 'max:20', 'regex:/^[!-~]*$/D'],
            'separator' => ['nullable', 'string', 'max:3', 'regex:/^[!-~]*$/D'],
            'start_number' => ['required', 'integer', 'min:0', 'max:999999999'],
            'count' => ['required', 'integer', 'min:1', 'max:1000'],
            'padding' => ['required', 'integer', 'min:1', 'max:12'],
            'paper_width' => ['required', 'numeric', 'min:20', 'max:300'],
            'label_width' => ['required', 'numeric', 'min:10', 'lte:paper_width'],
            'label_height' => ['required', 'numeric', 'min:8', 'max:200'],
            'position' => ['required', 'in:left,center,right'],
            'x_offset' => ['required', 'numeric', 'min:0', 'max:100'],
            'y_offset' => ['required', 'numeric', 'min:0', 'max:100'],
            'gap' => ['required', 'numeric', 'min:0', 'max:30'],
            'copies' => ['required', 'integer', 'min:1', 'max:10'],
            'barcode_format' => ['required', 'in:CODE128,CODE39'],
            'barcode_height' => ['required', 'numeric', 'min:3', 'max:100'],
            'barcode_margin' => ['required', 'numeric', 'min:0', 'max:10'],
            'show_text' => ['required', 'boolean'],
        ];
    }

    public function generatorData(): array
    {
        $data = $this->validated();
        if ($data['label_height'] < $data['barcode_height'] + 2 * $data['barcode_margin'] + ($data['show_text'] ? 4 : 0)
            || $data['paper_width'] < $data['label_width'] + $data['x_offset']
            || 2 * $data['barcode_margin'] >= $data['label_width']) {
            throw ValidationException::withMessages(['label_height' => 'Barcode and offsets must fit inside the label and paper dimensions.']);
        }

        return $data;
    }
}
