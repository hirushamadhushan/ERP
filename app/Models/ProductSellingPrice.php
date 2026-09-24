<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductSellingPrice extends Model { protected $fillable=['selling_price_group_id','selling_price']; public function group(){return $this->belongsTo(SellingPriceGroup::class,'selling_price_group_id');} }
