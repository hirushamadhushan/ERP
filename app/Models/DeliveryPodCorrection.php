<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryPodCorrection extends Model { protected $fillable=['status','reason','original_outcome','requested_by','approved_by','approved_at','reversal_transaction_id']; protected function casts():array{return ['original_outcome'=>'array','approved_at'=>'datetime'];} public function consignment(){return $this->belongsTo(DeliveryConsignment::class);} }
