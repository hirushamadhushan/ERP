<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['variation_template_id', 'value', 'sku', 'purchase_price', 'purchase_price_inc', 'margin', 'selling_price', 'image_path'];

    public function template() { return $this->belongsTo(VariationTemplate::class, 'variation_template_id'); }
}
