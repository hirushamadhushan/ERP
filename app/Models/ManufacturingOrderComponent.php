<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ManufacturingOrderComponent extends Model {public $timestamps=false;protected $fillable=['product_id','product_variant_id','product_lot_id','quantity','unit_cost','total_cost'];public function product(){return $this->belongsTo(Product::class);}public function productVariant(){return $this->belongsTo(ProductVariant::class);}public function lot(){return $this->belongsTo(ProductLot::class,'product_lot_id');}}
