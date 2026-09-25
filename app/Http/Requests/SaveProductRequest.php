<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends BaseFormRequest
{
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
            $selectedLocations = array_map('intval', $this->input('location_ids', []));
            foreach (array_keys($this->input('location_details', [])) as $locationId) {
                if (! in_array((int) $locationId, $selectedLocations, true)) {
                    $validator->errors()->add('location_details', 'Rack details may only be entered for selected business locations.');
                    break;
                }
            }
            $primaryId = (int) $this->input('unit_id');
            foreach (['purchase_unit_id', 'secondary_unit_id'] as $field) {
                if (! $this->input($field)) continue;
                $unit = \App\Models\Unit::find($this->input($field));
                if ($unit && $unit->id !== $primaryId && (int) $unit->base_unit_id !== $primaryId) {
                    $validator->errors()->add($field, 'Choose the primary unit or one of its direct sub-units.');
                }
            }
        }];
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/D', Rule::unique('products', 'sku_key')->ignore($product?->id)],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
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
            'not_for_selling' => ['required', 'boolean'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
            'description' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'variant_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'brochure' => ['nullable', 'file', 'mimes:pdf,csv,txt,zip,doc,docx,jpg,jpeg,png', 'max:5120'],
            'weight' => ['nullable', 'string', 'max:100'],
            'expiry_period' => ['nullable', 'integer', 'min:1', 'max:1200', 'required_with:expiry_period_type'],
            'expiry_period_type' => ['nullable', Rule::in(['days', 'months']), 'required_with:expiry_period'],
            'location_details' => ['nullable', 'array'],
            'location_details.*.rack' => ['nullable', 'string', 'max:100'],
            'location_details.*.row' => ['nullable', 'string', 'max:100'],
            'location_details.*.position' => ['nullable', 'string', 'max:100'],
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
