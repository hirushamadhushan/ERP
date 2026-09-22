<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentAccountTypeRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }
    public function rules(): array
    {
        $type = $this->route('paymentAccountType');
        return [
            'name'=>['required','string','max:80', Rule::unique('payment_account_types')->ignore($type?->id)],
            'parent_id'=>['nullable','integer',Rule::exists('payment_account_types','id')->whereNull('parent_id'), Rule::notIn([$type?->id]), function($attribute,$value,$fail) use($type) {
                if ($value && $type?->children()->exists()) $fail('An account type with children cannot become a subtype.');
            }],
        ];
    }
}
