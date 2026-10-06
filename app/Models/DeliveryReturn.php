<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryReturn extends Model
{
    protected $fillable = ['consignment_id', 'number', 'status', 'created_by', 'unloading_transfer_id', 'completed_at'];

    protected function casts(): array { return ['completed_at' => 'datetime']; }

    public function consignment() { return $this->belongsTo(DeliveryConsignment::class, 'consignment_id'); }
    public function lines() { return $this->hasMany(DeliveryReturnLine::class, 'delivery_return_id'); }
    public function unloadingTransfer() { return $this->belongsTo(DeliveryTransfer::class, 'unloading_transfer_id'); }
}
