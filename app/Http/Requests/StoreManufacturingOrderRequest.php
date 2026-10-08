<?php
namespace App\Http\Requests;
use Illuminate\Validation\Rule;
class StoreManufacturingOrderRequest extends BaseFormRequest {
 public function rules():array{return ['product_id'=>['required','integer',Rule::exists('products','id')->where(fn($q)=>$q->where('is_manufacturable',1)->where('track_lots',1)->where('manage_stock',1)->where('is_active',1))],'bill_of_material_id'=>['required','integer','exists:bills_of_materials,id'],'location_id'=>['required','integer','exists:locations,id'],'quantity'=>['required','numeric','gt:0','max:999999999','decimal:0,4'],'manufactured_at'=>['required','date','before_or_equal:today'],'expires_at'=>['nullable','date','after:manufactured_at'],'selling_price'=>['required','numeric','min:0','max:999999999','decimal:0,4'],'notes'=>['nullable','string','max:2000'],'expenses'=>['nullable','array','max:30'],'expenses.*.name'=>['required_with:expenses.*.amount','string','max:150'],'expenses.*.amount'=>['required_with:expenses.*.name','numeric','min:0','max:999999999','decimal:0,4']];}
}
