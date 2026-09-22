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
    public function getValueAttribute() { return $this->variationValue?->value; }
    public function getVariationTemplateIdAttribute() { return $this->variationValue?->variation_template_id; }
}
