<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryConsignmentLine extends Model
{
    public $timestamps = false;
    protected $fillable = ['transfer_line_id', 'delivered_quantity', 'damaged_quantity', 'missing_quantity', 'return_quantity', 'remarks'];
    public function transferLine() { return $this->belongsTo(DeliveryTransferLine::class, 'transfer_line_id'); }
}
