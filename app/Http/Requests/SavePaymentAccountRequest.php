<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentAccountRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }
    public function rules(): array
    {
        $account = $this->route('paymentAccount');
        return [
            'name'=>['required','string','max:120',Rule::unique('payment_accounts')->ignore($account?->id)],
            'account_number'=>['required','string','max:80',Rule::unique('payment_accounts')->ignore($account?->id)],
            'payment_account_type_id'=>['nullable','integer',Rule::exists('payment_account_types','id')->whereNull('parent_id')],
            'payment_account_sub_type_id'=>['nullable','integer','exists:payment_account_types,id'],
            'opening_balance'=>['required','numeric','between:-9999999999999999.99,9999999999999999.99'],
            'is_active'=>['nullable','boolean'],
            'details'=>['nullable','array','max:2'],
            'details.*.label'=>['nullable','string','max:80','required_with:details.*.value'],
            'details.*.value'=>['nullable','string','max:255','required_with:details.*.label'],
        ];
    }
}
