<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesOrderLine extends Model {
 protected $fillable=['product_id','product_lot_id','tax_rate_id','description','quantity','unit_price','sort_order'];
 public function product(){return $this->belongsTo(Product::class);}
 public function productLot(){return $this->belongsTo(ProductLot::class);}
}
