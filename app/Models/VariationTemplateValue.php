<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariationTemplateValue extends Model
{
    public $timestamps = false;
    protected $fillable = ['value', 'sort_order'];

    public function template() { return $this->belongsTo(VariationTemplate::class, 'variation_template_id'); }
    public function productVariants() { return $this->hasMany(ProductVariant::class, 'variation_template_value_id'); }
    public function combinationVariants() { return $this->belongsToMany(ProductVariant::class, 'product_variant_values'); }
}
