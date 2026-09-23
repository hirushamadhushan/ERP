<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class SaveLocationReceiptSettingsRequest extends FormRequest { public function authorize():bool{return auth()->check();} public function rules():array{return ['auto_print_invoice'=>['required','boolean'],'printer_type'=>['required',Rule::in(['browser','configured'])],'receipt_printer_id'=>['nullable','required_if:printer_type,configured','exists:receipt_printers,id'],'invoice_layout_id'=>['required','exists:invoice_layouts,id'],'invoice_scheme_id'=>['required','exists:invoice_schemes,id']];} }
