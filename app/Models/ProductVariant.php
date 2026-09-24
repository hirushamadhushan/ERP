<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use \App\Models\Concerns\HasCalculatedPrices;
    protected $appends=['purchase_price_inc','margin'];
    public function product() { return $this->belongsTo(Product::class); }
    protected $fillable = ['variation_template_value_id', 'sku', 'purchase_price', 'selling_price', 'image_path'];

    public function variationValue() { return $this->belongsTo(VariationTemplateValue::class, 'variation_template_value_id'); }
    public function variationValues() { return $this->belongsToMany(VariationTemplateValue::class, 'product_variant_values')->with('template')->orderBy('variation_template_values.id'); }
    public function locationStocks() { return $this->hasMany(ProductVariantLocationStock::class); }
    public function getValueAttribute() { return $this->relationLoaded('variationValues') ? $this->variationValues->pluck('value')->implode(' / ') : $this->variationValue?->value; }
    public function getVariationTemplateIdAttribute() { return $this->variationValue?->variation_template_id; }
}
