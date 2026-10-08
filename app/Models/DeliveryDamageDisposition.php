<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryDamageDisposition extends Model { protected $fillable=['quarantine_location_id','inventory_transaction_id','quantity','status']; public function location(){return $this->belongsTo(Location::class,'quarantine_location_id');} }
