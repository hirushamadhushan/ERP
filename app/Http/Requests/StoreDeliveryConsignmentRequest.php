<?php

namespace App\Http\Requests;

class StoreDeliveryConsignmentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'loading_transfer_id' => ['required', 'integer', 'exists:delivery_transfers,id'],
            'customer_id' => ['required', 'integer', 'exists:contacts,id'],
            'scheduled_at' => ['nullable', 'date'],
            'delivery_address' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
