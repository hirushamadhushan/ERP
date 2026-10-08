<?php
namespace App\Services;

use App\Models\{DeliveryConsignment, DeliveryTransfer, DeliveryVehicle, InventoryMovement, InventoryTransaction, Location, Product, ProductLot, ProductSerialNumber, ProductStockItem};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryStockService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['transfer' => $message]);
    }

    public function transfer(array $data, int $userId): DeliveryTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $vehicle = DeliveryVehicle::lockForUpdate()->findOrFail($data['vehicle_id']);
            $payloadHash = hash('sha256', json_encode($data));
            $previous = DeliveryTransfer::where('request_key', $data['request_key'])->first();
            if ($previous) {
                if ($previous->payload_hash !== $payloadHash || $previous->transaction->created_by != $userId) $this->fail('This request has already been used. Reload the form.');
                return $previous;
            }
            $loading = $data['direction'] === 'loading';
            $blockingStatuses = $loading
                ? [DeliveryConsignment::STATUS_IN_TRANSIT, DeliveryConsignment::STATUS_ARRIVED]
                : DeliveryConsignment::ACTIVE_STATUSES;
            $hasActiveDelivery = DeliveryConsignment::whereIn('status', $blockingStatuses)
                ->whereHas('loadingTransfer', fn ($query) => $query->where('vehicle_id', $vehicle->id))
                ->exists();
            if ($hasActiveDelivery) {
                $this->fail($loading
                    ? 'This vehicle has departed on a customer route. Complete it before loading more stock.'
                    : 'This vehicle has an active customer delivery. Record its POD before unloading stock to a warehouse.');
            }
            $warehouse = Location::findOrFail($data['warehouse_id']);
            if (! $warehouse->is_active || DB::table('delivery_vehicle_stores')->where('location_id', $warehouse->id)->exists()) $this->fail('Select an active warehouse or branch location.');
            $store = $vehicle->stores()->firstOrFail();
            $driver = $vehicle->drivers()->first();
            if ($loading && (! $vehicle->is_active || ! $driver?->canDrive())) $this->fail('Loading requires an active vehicle and assigned driver with a valid license number.');
            if ($loading && $vehicle->hasExpiredDocuments()) $this->fail('The vehicle has an expired insurance or revenue license document.');
            $source = $loading ? $warehouse->id : $store->id;
            $destination = $loading ? $store->id : $warehouse->id;
            $products = Product::with('unit')->whereIn('id', array_column($data['lines'], 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $transaction = InventoryTransaction::create(['transaction_type' => 'vehicle_'.$data['direction'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null, 'created_by' => $userId, 'occurred_at' => now()]);
            $transfer = DeliveryTransfer::create(['request_key' => $data['request_key'], 'payload_hash' => $payloadHash, 'vehicle_id' => $vehicle->id, 'warehouse_id' => $warehouse->id, 'driver_id' => $driver?->id, 'inventory_transaction_id' => $transaction->id, 'direction' => $data['direction']]);
            foreach ($data['lines'] as $line) {
                $product = $products->get($line['product_id']);
                if (! $product || ! $product->manage_stock || $product->product_type === 'combo' || ($loading && ! $product->is_active)) $this->fail('Choose an active stock-managed product; bundles cannot be transferred.');
                if (! $product->locations()->where('locations.id', $source)->exists()) $this->fail('The product is not stocked at the source location.');
                $variant = ! empty($line['variant_id']) ? $product->variants()->find($line['variant_id']) : null;
                if (($product->product_type === 'variable' && ! $variant) || ($product->product_type !== 'variable' && ! empty($line['variant_id']))) $this->fail('Select a valid variation for this product.');
                $scaled = (int) round((float) $line['quantity'] * 10000);
                if (! $product->unit?->allow_decimal && $scaled % 10000 !== 0) $this->fail('This product requires whole-number quantities.');
                $quantity = $scaled / 10000;
                $items = $product->stockItems();
                $item = $variant ? $items->whereHas('variant', fn ($q) => $q->whereKey($variant->id))->first() : $items->whereDoesntHave('variant')->first();
                if (! $item) {
                    $item = ProductStockItem::create(['product_id' => $product->id]);
                    if ($variant) $item->variant()->attach($variant->id);
                }
                $record = $transfer->lines()->create(['stock_item_id' => $item->id, 'quantity' => $quantity]);
                $product->locations()->syncWithoutDetaching([$destination]);
                if ($product->enable_serial) {
                    $ids = array_values(array_unique($line['serial_ids'] ?? []));
                    $serials = ProductSerialNumber::forProduct($product->id)->where('product_variant_id', $variant?->id)->where('location_id', $source)->where('status', 'available')->whereIn('id', $ids)->lockForUpdate()->get();
                    if (count($ids) !== (int) $quantity || $quantity != (int) $quantity || $serials->count() !== count($ids)) $this->fail('Select exactly one available serial per unit at the source location.');
                    if ($product->track_lots) {
                        $lotIds = $serials->pluck('product_lot_id')->filter()->unique();
                        if ($lotIds->count() !== 1 || (int) $lotIds->first() !== (int) ($line['lot_id'] ?? 0)) $this->fail('All selected serials must belong to the selected production lot.');
                        $lot = ProductLot::where('product_stock_item_id', $item->id)->find($line['lot_id']);
                        if (! $lot) $this->fail('Select the production lot belonging to these serials.');
                        if ($loading && $lot->expires_at && $lot->expires_at->toDateString() < now()->toDateString()) $this->fail('Expired lots cannot be loaded.');
                        $available = InventoryMovement::where('product_lot_id', $lot->id)->where('location_id', $source)->sum('quantity_delta');
                        if ((int) round((float) $available * 10000) < $scaled) $this->fail('Insufficient lot stock at the source location.');
                        foreach ([$source => -$quantity, $destination => $quantity] as $locationId => $delta) InventoryMovement::create(['inventory_transaction_id' => $transaction->id, 'product_lot_id' => $lot->id, 'location_id' => $locationId, 'quantity_delta' => $delta, 'created_at' => now()]);
                        $record->lots()->attach($lot->id);
                    } elseif (! empty($line['lot_id'])) $this->fail('Lots cannot be supplied for this serial product.');
                    foreach ($serials as $serial) $serial->update(['location_id' => $destination]);
                    $record->serials()->attach($ids);
                } elseif ($product->track_lots) {
                    if (! empty($line['serial_ids'])) $this->fail('Serials cannot be supplied for a lot product.');
                    $lot = ProductLot::where('product_stock_item_id', $item->id)->find($line['lot_id'] ?? null);
                    if (! $lot) $this->fail('Select a lot belonging to the chosen product and variation.');
                    if ($loading && $lot->expires_at && $lot->expires_at->toDateString() < now()->toDateString()) $this->fail('Expired lots cannot be loaded.');
                    $available = InventoryMovement::where('product_lot_id', $lot->id)->where('location_id', $source)->sum('quantity_delta');
                    if ((int) round((float) $available * 10000) < $scaled) $this->fail('Insufficient lot stock at the source location.');
                    foreach ([$source => -$quantity, $destination => $quantity] as $locationId => $delta) InventoryMovement::create(['inventory_transaction_id' => $transaction->id, 'product_lot_id' => $lot->id, 'location_id' => $locationId, 'quantity_delta' => $delta, 'created_at' => now()]);
                    $record->lots()->attach($lot->id);
                } else {
                    if (! empty($line['lot_id']) || ! empty($line['serial_ids'])) $this->fail('This product does not use lots or serials.');
                    if ($variant) {
                        $from = $variant->locationStocks()->where('location_id', $source)->first();
                        $available = $from?->opening_quantity ?? 0;
                    } else {
                        $available = $product->locations()->where('locations.id', $source)->first()->pivot->opening_quantity;
                    }
                    if ((int) round((float) $available * 10000) < $scaled) $this->fail('Insufficient stock at the source location.');
                    if ($variant) {
                        $from->decrement('opening_quantity', $quantity);
                        $to = $variant->locationStocks()->firstOrCreate(['location_id' => $destination], ['opening_quantity' => 0]);
                        $to->increment('opening_quantity', $quantity);
                    } else {
                        DB::table('location_product')->where('product_id', $product->id)->where('location_id', $source)->decrement('opening_quantity', $quantity);
                        DB::table('location_product')->where('product_id', $product->id)->where('location_id', $destination)->increment('opening_quantity', $quantity);
                    }
                }
            }
            return $transfer;
        }, 3);
    }
}
