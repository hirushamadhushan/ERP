<?php
namespace App\Http\Requests;
use Illuminate\Validation\Rule;
class StoreManufacturingOrderRequest extends BaseFormRequest {
 protected function prepareForValidation():void{
  $expenses=collect($this->input('expenses',[]))->filter(function($expense){
   if(!is_array($expense))return true;
   return filled($expense['name']??null)||filled($expense['amount']??null);
  })->values()->all();
  $this->merge(['expenses'=>$expenses]);
 }
 public function rules():array{return ['product_id'=>['required','integer',Rule::exists('products','id')->where(fn($q)=>$q->where('is_raw_material',0)->where('product_type','single')->where('manage_stock',1)->where('is_active',1))],'bill_of_material_id'=>['nullable','integer','exists:bills_of_materials,id'],'location_id'=>['required','integer','exists:locations,id'],'quantity'=>['required','numeric','gt:0','max:999999999','decimal:0,4'],'manufactured_at'=>['required','date','before_or_equal:today'],'selling_price'=>['required','numeric','min:0','max:999999999','decimal:0,4'],'notes'=>['nullable','string','max:2000'],'components'=>['required_without:bill_of_material_id','array','min:1','max:100'],'components.*.product_id'=>['required','integer',Rule::exists('products','id')->where(fn($q)=>$q->where('is_raw_material',1)->where('enable_serial',0)->where('manage_stock',1)->where('is_active',1)->whereIn('product_type',['single','variable']))],'components.*.product_variant_id'=>['nullable','integer','exists:product_variants,id'],'components.*.source_lot_number'=>['nullable','string','max:100'],'components.*.quantity_per_unit'=>['required','numeric','gt:0','max:999999999','decimal:0,4'],'expenses'=>['nullable','array','max:30'],'expenses.*.name'=>['required_with:expenses.*.amount','string','max:150'],'expenses.*.amount'=>['required_with:expenses.*.name','numeric','min:0','max:999999999','decimal:0,4']];}
}
