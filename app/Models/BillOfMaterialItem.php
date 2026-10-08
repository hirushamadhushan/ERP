<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillOfMaterialItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['product_id', 'product_variant_id', 'quantity', 'position'];
    public function billOfMaterial() { return $this->belongsTo(BillOfMaterial::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function productVariant() { return $this->belongsTo(ProductVariant::class); }
}
