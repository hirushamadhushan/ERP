<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SellingPriceGroup extends Model { protected $fillable=['name']; public function prices(){return $this->hasMany(ProductSellingPrice::class);} }
