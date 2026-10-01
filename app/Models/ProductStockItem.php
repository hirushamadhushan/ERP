<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockItem extends Model
{
    protected $fillable = ['product_id'];

    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsToMany(ProductVariant::class, 'product_stock_item_variants', 'product_stock_item_id', 'product_variant_id')->withTimestamps(); }
    public function lots() { return $this->hasMany(ProductLot::class); }
}
