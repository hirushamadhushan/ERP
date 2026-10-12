<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        $rawMaterial = $this->boolean('is_raw_material');
        $product = $this->route('product');

        // Manufacturing setup is derived from the product's role. Users only
        // decide whether it is a raw material; output lot tracking is automatic.
        $this->merge([
            'is_raw_material' => $rawMaterial,
            'is_manufacturable' => $rawMaterial ? false : (bool) ($product?->is_manufacturable ?? false),
            'track_lots' => (bool) ($product?->track_lots ?? false),
        ]);
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;
            if ($this->input('subcategory_id')) {
                $category = \App\Models\Category::find($this->input('subcategory_id'));
                if (!$category?->parent_id || $category->category_type !== 'product' || $category->rootCategoryId() !== (int) $this->input('category_id')) {
                    $validator->errors()->add('subcategory_id', 'Choose a sub-category under the selected main category.');
                }
            }
            $primaryId = (int) $this->input('unit_id');
            if ($this->input('purchase_unit_id')) {
                $purchaseUnit = \App\Models\Unit::find($this->input('purchase_unit_id'));
                if ($purchaseUnit && (int) $purchaseUnit->base_unit_id !== $primaryId) {
                    $validator->errors()->add('purchase_unit_id', 'Choose a sub-unit belonging to the selected primary unit.');
                }
            }
            if ($this->input('secondary_unit_id')) {
                $secondaryUnit = \App\Models\Unit::find($this->input('secondary_unit_id'));
                if ($secondaryUnit && $secondaryUnit->id !== $primaryId && (int) $secondaryUnit->base_unit_id !== $primaryId) {
                    $validator->errors()->add('secondary_unit_id', 'Choose the primary unit or one of its direct sub-units.');
                }
            }
            if ($this->boolean('is_raw_material') && ! $this->boolean('manage_stock')) $validator->errors()->add('is_raw_material','A raw material must use stock management.');
            if ($this->boolean('is_manufacturable') && (!$this->boolean('manage_stock') || !$this->boolean('track_lots'))) $validator->errors()->add('is_manufacturable','A manufactured product must manage stock and track production lots.');
        }];
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/D', Rule::unique('products', 'sku_key')->ignore($product?->id)],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->whereNull('base_unit_id')],
            'purchase_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'secondary_unit_id' => ['nullable', 'integer', 'different:unit_id', 'exists:units,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'warranty_id' => ['nullable', 'integer', 'exists:warranties,id'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['required', 'integer', 'distinct', Rule::exists('locations', 'id')->where(function ($query) use ($product) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('locations', 'is_active')) {
                    $assignedIds = $product?->locations()->pluck('locations.id')->all() ?? [];
                    $query->where(fn ($locations) => $locations->where('is_active', true)->orWhereIn('id', $assignedIds));
                }
            })],
            'barcode_type' => ['required', Rule::in(['CODE128', 'CODE39', 'EAN13', 'UPCA'])],
            'manage_stock' => ['required', 'boolean'],
            'enable_serial' => ['required', 'boolean'],
            'track_lots' => ['required', 'boolean'],
            'is_raw_material' => ['required','boolean'],
            'is_manufacturable' => ['required','boolean'],
            'not_for_selling' => ['required', 'boolean'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'description' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variant_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'brochure' => ['nullable', 'file', 'mimes:pdf,csv,txt,zip,doc,docx,jpg,jpeg,png', 'max:5120'],
            'weight' => ['nullable', 'string', 'max:100'],
            'expiry_period' => ['nullable', 'integer', 'min:1', 'max:1200', 'required_with:expiry_period_type'],
            'expiry_period_type' => ['nullable', Rule::in(['days', 'months']), 'required_with:expiry_period'],
            'custom_fields' => ['nullable', 'array', 'max:4'],
            'custom_fields.*' => ['nullable', 'string', 'max:255'],
            'product_type' => ['required', Rule::in(['single', 'variable', 'combo'])],
            'tax_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where(function ($query) {
                $query->where('is_tax_group', 1)->orWhere('for_tax_group', 0);
            })],
            'tax_rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,3'],
            'selling_price_tax_type' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'margin' => ['nullable', 'numeric', 'min:-100', 'max:999999'],
            'our_price' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'save_action' => ['required', Rule::in(['save', 'another', 'opening', 'prices'])],
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
