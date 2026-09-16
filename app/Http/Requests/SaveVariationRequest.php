<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveVariationRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('variation_templates', 'name')->ignore($this->route('variation')?->id)],
            'values' => ['required'],
        ];
    }

    public function variationData(): array
    {
        $rawValues = $this->validated('values');
        $values = is_string($rawValues)
            ? explode(',', $rawValues)
            : (is_array($rawValues) ? $rawValues : []);
        $values = array_values(array_filter(array_map('trim', $values), fn ($value) => $value !== ''));

        if ($values === []) {
            throw ValidationException::withMessages(['values' => ['Please enter at least one variation value.']]);
        }

        return ['name' => $this->validated('name'), 'values' => $values];
    }
}
