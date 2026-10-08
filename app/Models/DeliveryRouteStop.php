<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryRouteStop extends Model { public $timestamps=false; protected $fillable=['consignment_id','sequence','status']; public function route(){return $this->belongsTo(DeliveryRoute::class);} public function consignment(){return $this->belongsTo(DeliveryConsignment::class);} }
