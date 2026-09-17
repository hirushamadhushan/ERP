<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/D', Rule::unique('products', 'sku_key')->ignore($product?->id)],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('parent_id', $this->input('category_id'))],
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['required', 'integer', 'distinct', Rule::exists('locations', 'id')->where(function ($query) use ($product) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('locations', 'is_active')) {
                    $assignedIds = $product?->locations()->pluck('locations.id')->all() ?? [];
                    $query->where(fn ($locations) => $locations->where('is_active', true)->orWhereIn('id', $assignedIds));
                }
            })],
            'barcode_type' => ['required', Rule::in(['CODE128', 'CODE39'])],
            'manage_stock' => ['required', 'boolean'],
            'enable_serial' => ['required', 'boolean'],
            'not_for_selling' => ['required', 'boolean'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'description' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variant_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'brochure' => ['nullable', 'file', 'mimes:pdf,csv,txt,zip,doc,docx,jpg,jpeg,png', 'max:5120'],
            'weight' => ['nullable', 'string', 'max:100'],
            'custom_fields' => ['nullable', 'array', 'max:4'],
            'custom_fields.*' => ['nullable', 'string', 'max:255'],
            'product_type' => ['required', Rule::in(['single', 'variable', 'combo'])],
            'tax_rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,3'],
            'selling_price_tax_type' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'margin' => ['nullable', 'numeric', 'min:-100', 'max:999999'],
            'our_price' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'save_action' => ['required', Rule::in(['save', 'another', 'opening'])],
            'variation_template_id' => ['nullable', 'required_if:product_type,variable', 'integer', 'exists:variation_templates,id'],
            'variants' => ['nullable', 'required_if:product_type,variable', 'array', 'min:1'],
            'variants.*.value' => ['required_if:product_type,variable', 'string', 'max:100', 'distinct'],
            'variants.*.sku' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/D', 'distinct', Rule::unique('product_variants', 'sku')->whereNot('product_id', $product?->id)],
            'variants.*.purchase_price' => ['required_if:product_type,variable', 'numeric', 'min:0', 'max:999999999'],
            'variants.*.selling_price' => ['required_if:product_type,variable', 'numeric', 'min:0', 'max:999999999'],
            'variant_images' => ['nullable', 'array'],
            'variant_images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'combo_items' => ['nullable', 'required_if:product_type,combo', 'array', 'min:1'],
            'combo_items.*.product_id' => ['required_if:product_type,combo', 'integer', 'distinct', Rule::exists('products', 'id')->where(fn ($query) => $query->where('product_type', '!=', 'combo'))],
            'combo_items.*.quantity' => ['required_if:product_type,combo', 'numeric', 'gt:0', 'max:999999999'],
        ];
    }
}
