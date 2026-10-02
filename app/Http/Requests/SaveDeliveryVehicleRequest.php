<?php
namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveDeliveryVehicleRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['number' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('number')))]);
    }
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:40', Rule::unique('delivery_vehicles')->ignore($this->route('vehicle')?->id)],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'], 'model' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'fuel_type' => ['nullable', Rule::in(['petrol', 'diesel', 'electric', 'hybrid', 'other'])],
            'chassis_number' => ['nullable', 'string', 'max:100'], 'engine_number' => ['nullable', 'string', 'max:100'],
            'insurance_expires_at' => ['nullable', 'date_format:Y-m-d'], 'revenue_license_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'], 'is_active' => ['required', 'boolean'],
        ];
    }
}
