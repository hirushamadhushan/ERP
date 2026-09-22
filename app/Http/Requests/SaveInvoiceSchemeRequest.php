<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceSchemeRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }
    public function rules(): array
    {
        $scheme = $this->route('invoiceScheme');
        return [
            'name'=>['required','string','max:100',Rule::unique('invoice_schemes')->ignore($scheme?->id)],
            'format'=>['required',Rule::in(['number','year_number'])],
            'prefix'=>['nullable','string','max:30'],
            'start_number'=>['required','integer','min:0','max:999999999999'],
            'number_of_digits'=>['required','integer','between:1,12'],
            'is_default'=>['nullable','boolean'],
        ];
    }
}
