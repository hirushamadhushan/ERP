<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantLocationStock extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_variant_id', 'location_id', 'opening_quantity', 'opening_unit_cost',
        'opening_expiry_date', 'opening_stocked_at', 'opening_note',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
