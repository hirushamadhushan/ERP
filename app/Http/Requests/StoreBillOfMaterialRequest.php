<?php
namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreBillOfMaterialRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $id = $this->route('bom')?->id;
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('bills_of_materials', 'code')->ignore($id)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('is_active', 1)->where('manage_stock',1)->where('is_raw_material',0)->where('product_type','single'))],
            'output_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,4'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('is_raw_material', 1)->where('enable_serial', 0)->where('is_active', 1)->whereIn('product_type',['single','variable']))],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,4'],
        ];
    }
}
