<?php

namespace App\Http\Requests;

class CompleteDeliveryConsignmentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'receiver_name' => ['required', 'string', 'max:150'],
            'receiver_phone' => ['nullable', 'string', 'max:40'],
            'proof_notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.id' => ['required', 'integer', 'distinct'],
            'lines.*.delivered' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.damaged' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.missing' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
            'signature_data' => ['nullable', 'string', 'max:2800000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
