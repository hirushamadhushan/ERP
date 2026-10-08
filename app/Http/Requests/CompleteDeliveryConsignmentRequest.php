<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class CompleteDeliveryConsignmentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'receiver_name' => ['required', 'string', 'max:150'],
            'receiver_phone' => ['nullable', 'string', 'max:40'],
            'receiver_id_reference' => ['nullable', 'string', 'max:100'],
            'receiver_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'receiver_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:receiver_latitude'],
            'proof_notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.id' => ['required', 'integer', 'distinct'],
            'lines.*.delivered' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.damaged' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.missing' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
            'lines.*.serial_outcomes' => ['nullable', 'array', 'max:1000'],
            'lines.*.serial_outcomes.*.serial_id' => ['required', 'integer', 'distinct', 'exists:product_serial_numbers,id'],
            'lines.*.serial_outcomes.*.outcome' => ['required', 'in:delivered,damaged,missing'],
            'lines.*.serial_outcomes.*.remarks' => ['nullable', 'string', 'max:500'],
            'signature_data' => ['nullable', 'string', 'max:2800000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photo_caption' => ['nullable', 'string', 'max:250'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (filled($this->input('receiver_phone')) && ! preg_match('/^\+?[0-9 ()-]{7,40}$/', (string) $this->input('receiver_phone'))) {
                $validator->errors()->add('receiver_phone', 'Enter a valid receiver phone number.');
            }
            if (filled($this->input('receiver_longitude')) && blank($this->input('receiver_latitude'))) $validator->errors()->add('receiver_latitude', 'Latitude is required with longitude.');
            $hasSignature = filled($this->input('signature_data'));
            $hasPhotos = count(array_filter($this->file('photos', []))) > 0;
            if (! $hasSignature && ! $hasPhotos) {
                $validator->errors()->add('proof', 'Add a receiver signature or at least one proof photo before submitting POD.');
            }

            foreach ((array) $this->input('lines', []) as $index => $line) {
                $serialOutcomes = collect($line['serial_outcomes'] ?? []);
                $hasDiscrepancy = (float) ($line['damaged'] ?? 0) > 0 || (float) ($line['missing'] ?? 0) > 0
                    || $serialOutcomes->contains(fn ($outcome) => in_array($outcome['outcome'] ?? null, ['damaged', 'missing'], true));
                if ($hasDiscrepancy && blank($line['remarks'] ?? null)) {
                    $validator->errors()->add("lines.$index.remarks", 'Explain every damaged or short quantity in Remarks.');
                }
            }
        }];
    }
}
