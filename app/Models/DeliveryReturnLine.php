<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryReturnLine extends Model
{
    public $timestamps = false;
    protected $fillable = ['consignment_line_id', 'quantity'];

    public function consignmentLine() { return $this->belongsTo(DeliveryConsignmentLine::class, 'consignment_line_id'); }
}
