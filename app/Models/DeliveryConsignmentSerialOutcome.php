<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryConsignmentSerialOutcome extends Model
{
    public $timestamps = false;
    protected $fillable = ['serial_id', 'outcome', 'remarks'];
    public function consignmentLine() { return $this->belongsTo(DeliveryConsignmentLine::class); }
    public function serial() { return $this->belongsTo(ProductSerialNumber::class, 'serial_id'); }
}
