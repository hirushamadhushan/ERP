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

    public function scopeAvailableForConsignment($query)
    {
        return $query->where('direction', self::DIRECTION_LOADING)
            ->whereDoesntHave('consignment')
            ->whereNotIn('vehicle_id', DB::table('delivery_transfers as active_transfers')
                ->join('delivery_consignments as active_deliveries', 'active_deliveries.loading_transfer_id', '=', 'active_transfers.id')
                ->whereIn('active_deliveries.status', DeliveryConsignment::ACTIVE_STATUSES)
                ->select('active_transfers.vehicle_id'));
    }

    /**
     * Confirm that every unit recorded by this loading transfer is still on the
     * assigned vehicle. Loading records are historical, so a later warehouse
     * unload must make the record unavailable for a customer delivery.
     */
    public function hasStockAvailableForConsignment(): bool
    {
        if ($this->direction !== self::DIRECTION_LOADING) return false;

        $this->loadMissing([
            'vehicle.stores', 'lines.stockItem.variant', 'lines.lots', 'lines.serials',
        ]);
        $storeId = $this->vehicle?->stores->first()?->id;
        if (! $storeId || $this->lines->isEmpty()) return false;

        foreach ($this->lines as $line) {
            $required = (int) round((float) $line->quantity * 10000);
            if ($line->serials->isNotEmpty()) {
                if ($line->serials->count() * 10000 !== $required) return false;
                if ($line->serials->contains(fn ($serial) =>
                    (int) $serial->location_id !== (int) $storeId || $serial->status !== ProductSerialNumber::AVAILABLE
                )) return false;
                continue;
            }
            if ($line->lots->isNotEmpty()) {
                $available = InventoryMovement::where('product_lot_id', $line->lots->first()->id)
                    ->where('location_id', $storeId)->sum('quantity_delta');
                if ((int) round((float) $available * 10000) < $required) return false;
                continue;
            }

            $variant = $line->stockItem->variant->first();
            $available = $variant
                ? DB::table('product_variant_location_stocks')->where('product_variant_id', $variant->id)->where('location_id', $storeId)->value('opening_quantity')
                : DB::table('location_product')->where('product_id', $line->stockItem->product_id)->where('location_id', $storeId)->value('opening_quantity');
            if ((int) round((float) ($available ?? 0) * 10000) < $required) return false;
        }

        return true;
    }
}
