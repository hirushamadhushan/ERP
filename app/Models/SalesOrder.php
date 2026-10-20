<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesOrder extends Model {
 protected $fillable=['order_number','customer_id','location_id','salesperson_id','commission_agent_id','order_date','delivery_date','status','total_amount','terms','created_by'];
 protected function casts():array{return ['order_date'=>'date','delivery_date'=>'date','total_amount'=>'decimal:2'];}
 public function customer(){return $this->belongsTo(Contact::class);}
 public function salesperson(){return $this->belongsTo(User::class);}
 public function lines(){return $this->hasMany(SalesOrderLine::class)->orderBy('sort_order');}
}
