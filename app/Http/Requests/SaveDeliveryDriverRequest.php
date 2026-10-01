<?php
namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SaveDeliveryDriverRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        $license = strtoupper(trim((string) $this->input('license_number')));
        $this->merge(['license_number' => $license ?: null]);
    }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'], 'identity_number' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:80', Rule::unique('delivery_drivers')->ignore($this->route('driver')?->id)],
            'license_expires_at' => ['nullable', 'date_format:Y-m-d'], 'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'], 'vehicle_id' => ['nullable', 'integer', 'exists:delivery_vehicles,id'],
        ];
    }
}
