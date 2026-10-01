<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTransfer extends Model
{
    protected $fillable = ['request_key', 'payload_hash', 'vehicle_id', 'warehouse_id', 'driver_id', 'inventory_transaction_id', 'direction'];
    public function vehicle() { return $this->belongsTo(DeliveryVehicle::class); }
    public function driver() { return $this->belongsTo(DeliveryDriver::class); }
    public function warehouse() { return $this->belongsTo(Location::class, 'warehouse_id'); }
    public function transaction() { return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id'); }
    public function lines() { return $this->hasMany(DeliveryTransferLine::class, 'transfer_id'); }
}
