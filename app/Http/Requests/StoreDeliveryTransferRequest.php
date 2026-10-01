<?php
namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreDeliveryTransferRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'], 'vehicle_id' => ['required', 'integer', 'exists:delivery_vehicles,id'],
            'warehouse_id' => ['required', 'integer', 'exists:locations,id'], 'direction' => ['required', Rule::in(['loading', 'unloading'])],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'lines.*.lot_id' => ['nullable', 'integer', 'exists:product_lots,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,4'],
            'lines.*.serial_ids' => ['nullable', 'array', 'max:1000'],
            'lines.*.serial_ids.*' => ['required', 'integer', 'distinct', 'exists:product_serial_numbers,id'],
        ];
    }
}
