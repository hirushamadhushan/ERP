<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeliveryTransfer extends Model
{
    public const DIRECTION_LOADING = 'loading';
    public const DIRECTION_UNLOADING = 'unloading';

    protected $fillable = ['request_key', 'payload_hash', 'vehicle_id', 'warehouse_id', 'driver_id', 'inventory_transaction_id', 'direction'];
    public function vehicle() { return $this->belongsTo(DeliveryVehicle::class); }
    public function driver() { return $this->belongsTo(DeliveryDriver::class); }
    public function warehouse() { return $this->belongsTo(Location::class, 'warehouse_id'); }
    public function transaction() { return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id'); }
    public function lines() { return $this->hasMany(DeliveryTransferLine::class, 'transfer_id'); }
    public function consignment() { return $this->hasOne(DeliveryConsignment::class, 'loading_transfer_id'); }
    public function returnNote() { return $this->hasOne(DeliveryReturn::class, 'unloading_transfer_id'); }

    public function scopeAvailableForConsignment($query)
    {
        return $query->where('direction', self::DIRECTION_LOADING)
            ->whereDoesntHave('consignment')
            ->whereNotIn('vehicle_id', DB::table('delivery_transfers as active_transfers')
                ->join('delivery_consignments as active_deliveries', 'active_deliveries.loading_transfer_id', '=', 'active_transfers.id')
                ->whereIn('active_deliveries.status', DeliveryConsignment::ACTIVE_STATUSES)
                ->select('active_transfers.vehicle_id'));
    }
}
