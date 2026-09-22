<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class SaveInvoiceLayoutRequest extends FormRequest
{
    public function authorize():bool{return auth()->check();}
    public function rules():array
    {
        $layout=$this->route('invoiceLayout');
        return ['name'=>['required','string','max:100',Rule::unique('invoice_layouts')->ignore($layout?->id)],'design'=>['required',Rule::in(['classic','elegant','detailed','columnize','slim','slim2'])],'logo'=>['nullable','image','mimes:jpg,jpeg,png','max:1024'],'show_logo'=>['nullable','boolean'],'header_text'=>['nullable','string','max:10000'],'footer_text'=>['nullable','string','max:10000'],'labels'=>['nullable','array'],'labels.*'=>['nullable','string','max:255'],'options'=>['nullable','array'],'options.*'=>['nullable','boolean'],'is_default'=>['nullable','boolean']];
    }
}
