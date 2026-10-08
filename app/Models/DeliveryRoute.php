<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryRoute extends Model { protected $fillable=['number','vehicle_id','status','scheduled_at','notes','created_by']; protected function casts():array{return ['scheduled_at'=>'datetime'];} public function vehicle(){return $this->belongsTo(DeliveryVehicle::class);} public function stops(){return $this->hasMany(DeliveryRouteStop::class,'route_id')->orderBy('sequence');} }
