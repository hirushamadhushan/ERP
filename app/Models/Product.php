<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use \App\Models\Concerns\HasCustomFieldValues;
    use \App\Models\Concerns\HasCalculatedPrices;
    protected $with=['selectedCategory'];
    protected $appends=['category_id','subcategory_id','custom_fields','purchase_price_inc','margin'];
    public function selectedCategory() { return $this->belongsTo(Category::class,'selected_category_id'); }
    public function getCategoryIdAttribute() { return $this->selectedCategory?->parent_id ?? $this->attributes['selected_category_id'] ?? null; }
    public function getSubcategoryIdAttribute() { return $this->selectedCategory?->parent_id ? $this->selected_category_id : null; }
    public function setCategoryIdAttribute($value): void { $this->attributes['selected_category_id']=$value; $this->unsetRelation('selectedCategory'); }
    public function setSubcategoryIdAttribute($value): void { if ($value) { $this->attributes['selected_category_id']=$value; $this->unsetRelation('selectedCategory'); } }

    protected $fillable = ['selected_category_id', 'name', 'code', 'unit_id', 'brand_id', 'category_id',
        'subcategory_id', 'barcode_type', 'manage_stock', 'enable_serial',
        'not_for_selling', 'alert_quantity', 'description', 'image_path',
        'brochure_path', 'brochure_name', 'variant_image_path', 'weight',
        'custom_fields', 'product_type', 'tax_rate', 'selling_price_tax_type',
        'purchase_price', 'selling_price', 'our_price'];

    protected function casts(): array
    {
        return ['manage_stock' => 'boolean', 'enable_serial' => 'boolean', 'not_for_selling' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function ($product) {
            $product->sku_key = strtolower($product->code);
        });
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class)->withPivot('opening_quantity');
    }

    public function serialNumbers()
    {
        return ProductSerialNumber::forProduct($this->id);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function comboItems()
    {
        return $this->belongsToMany(self::class, 'combo_product_items', 'combo_product_id', 'item_product_id')->withPivot('quantity');
    }
}
