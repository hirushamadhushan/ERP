<?php

namespace App\Services;

use App\Models\{Contact, DeliveryConsignment, DeliveryTransfer, DeliveryVehicle, InventoryMovement, InventoryTransaction, Product, ProductSerialNumber};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryConsignmentService
{
    public function create(array $data, int $userId): DeliveryConsignment
    {
        return DB::transaction(function () use ($data, $userId) {
            $transfer = DeliveryTransfer::with(['vehicle.stores', 'lines.stockItem.variant', 'lines.lots', 'lines.serials'])
                ->lockForUpdate()->findOrFail($data['loading_transfer_id']);
            if ($transfer->direction !== 'loading' || DeliveryConsignment::where('loading_transfer_id', $transfer->id)->exists()) {
                throw ValidationException::withMessages(['loading_transfer_id' => 'Choose an unused vehicle loading transfer.']);
            }
            // The vehicle lock serializes delivery allocation and stock-transfer checks.
            DeliveryVehicle::lockForUpdate()->findOrFail($transfer->vehicle_id);
            if (DeliveryConsignment::active()->whereHas('loadingTransfer', fn ($query) => $query->where('vehicle_id', $transfer->vehicle_id))->exists()) {
                throw ValidationException::withMessages(['loading_transfer_id' => 'This vehicle already has an active customer delivery. Complete it before creating another.']);
            }
            if (! $transfer->hasStockAvailableForConsignment()) {
                throw ValidationException::withMessages(['loading_transfer_id' => 'This loading record is no longer available because some or all of its stock has left the vehicle. Load the required stock again first.']);
            }
            $customer = Contact::findOrFail($data['customer_id']);
            if (! in_array($customer->type, ['customer', 'both'], true) || $customer->status !== 'active') throw ValidationException::withMessages(['customer_id' => 'Choose an active customer.']);
            $consignment = DeliveryConsignment::create([
                'number' => 'DN-'.str_pad((string) $transfer->id, 6, '0', STR_PAD_LEFT),
                'loading_transfer_id' => $transfer->id,
                'customer_id' => $customer->id,
                'sales_order_reference' => $data['sales_order_reference'] ?? null,
                'delivery_address' => $data['delivery_address'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
            foreach ($transfer->lines as $line) $consignment->lines()->create(['transfer_line_id' => $line->id]);
            $consignment->events()->create(['event' => 'loaded', 'user_id' => $userId, 'created_at' => now()]);
            return $consignment;
        });
    }

    public function depart(DeliveryConsignment $consignment, int $userId): void
    {
        DB::transaction(function () use ($consignment, $userId) {
            $record = DeliveryConsignment::with(['loadingTransfer.vehicle', 'loadingTransfer.driver'])->lockForUpdate()->findOrFail($consignment->id);
            if ($record->status !== 'loaded') throw ValidationException::withMessages(['status' => 'Only a loaded delivery can depart.']);
            $driver = $record->loadingTransfer->driver;
            if (! $record->loadingTransfer->vehicle->is_active || $record->loadingTransfer->vehicle->hasExpiredDocuments() || ! $driver?->canDrive()) {
                throw ValidationException::withMessages(['status' => 'Departure requires an active compliant vehicle and an active driver with a valid license number.']);
            }
            $record->update(['status' => 'in_transit', 'departed_at' => now()]);
            $record->events()->create(['event' => 'departed', 'user_id' => $userId, 'created_at' => now()]);
        });
    }

    public function complete(DeliveryConsignment $consignment, array $data, int $userId): void
    {
        DB::transaction(function () use ($consignment, $data, $userId) {
            $record = DeliveryConsignment::with(['loadingTransfer.vehicle.stores', 'lines.transferLine.stockItem.variant', 'lines.transferLine.stockItem.product', 'lines.transferLine.lots', 'lines.transferLine.serials'])
                ->lockForUpdate()->findOrFail($consignment->id);
            if ($record->status !== DeliveryConsignment::STATUS_ARRIVED || ! $record->arrived_at) throw ValidationException::withMessages(['status' => 'Record customer arrival before submitting proof of delivery.']);
            $storeId = $record->loadingTransfer->vehicle->stores->firstOrFail()->id;
            Product::whereIn('id', $record->lines->map(fn ($line) => $line->transferLine->stockItem->product_id)->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get();
            $submitted = collect($data['lines'])->keyBy('id');
            if ($submitted->count() !== $record->lines->count()) throw ValidationException::withMessages(['lines' => 'Provide an outcome for every loaded item.']);
            $results = [];
            $hasDelivered = false;
            $hasDifference = false;
            foreach ($record->lines as $line) {
                $input = $submitted->get($line->id);
                if (! $input) throw ValidationException::withMessages(['lines' => 'Unknown or missing delivery line.']);
                $quantities = [];
                foreach (['delivered', 'damaged', 'missing'] as $key) $quantities[$key] = (int) round((float) $input[$key] * 10000);
                if (collect($quantities)->contains(fn ($quantity) => $quantity < 0)) throw ValidationException::withMessages(['lines' => 'Outcome quantities cannot be negative.']);
                $loaded = (int) round((float) $line->transferLine->quantity * 10000);
                if (array_sum($quantities) !== $loaded) throw ValidationException::withMessages(['lines' => 'Delivered, damaged and missing quantities must equal the loaded quantity for every line.']);
                $product = $line->transferLine->stockItem->product;
                if (! $product->unit?->allow_decimal && collect($quantities)->contains(fn ($quantity) => $quantity % 10000 !== 0)) throw ValidationException::withMessages(['lines' => 'Use whole numbers for this product.']);
                if ($line->transferLine->serials->isNotEmpty() && collect($quantities)->filter()->count() > 1) throw ValidationException::withMessages(['lines' => 'A serial line must have one outcome. Split serials into individual loading lines.']);
                $hasDelivered = $hasDelivered || $quantities['delivered'] > 0;
                $hasDifference = $hasDifference || $quantities['damaged'] > 0 || $quantities['missing'] > 0;
                $results[] = [$line, $quantities, $input['remarks'] ?? null];
            }

            $transaction = InventoryTransaction::create(['transaction_type' => 'vehicle_delivery', 'reference' => $record->number, 'notes' => $data['proof_notes'] ?? null, 'created_by' => $userId, 'occurred_at' => now()]);
            foreach ($results as [$line, $quantities, $remarks]) {
                $source = $line->transferLine;
                $consumed = ($quantities['delivered'] + $quantities['damaged'] + $quantities['missing']) / 10000;
                if ($consumed > 0 && $source->lots->isNotEmpty()) {
                    $lot = $source->lots->first();
                    $available = InventoryMovement::where('product_lot_id', $lot->id)->where('location_id', $storeId)->sum('quantity_delta');
                    if ((int) round((float) $available * 10000) < $consumed * 10000) throw ValidationException::withMessages(['lines' => 'Insufficient lot stock on the vehicle.']);
                    InventoryMovement::create(['inventory_transaction_id' => $transaction->id, 'product_lot_id' => $lot->id, 'location_id' => $storeId, 'quantity_delta' => -$consumed, 'created_at' => now()]);
                } elseif ($consumed > 0 && $source->serials->isNotEmpty()) {
                    $serials = ProductSerialNumber::whereIn('id', $source->serials->pluck('id'))->lockForUpdate()->get();
                    if ($serials->count() !== (int) $consumed || $serials->contains(fn ($serial) => $serial->location_id !== $storeId || $serial->status !== 'available')) throw ValidationException::withMessages(['lines' => 'The loaded serial is no longer available on this vehicle.']);
                    foreach ($serials as $serial) {
                        if ($quantities['delivered']) $serial->markSold($transaction->id);
                        elseif ($quantities['damaged']) $serial->markDamaged();
                        else $serial->markMissing();
                    }
                } elseif ($consumed > 0) {
                    $variant = $source->stockItem->variant->first();
                    $stock = $variant
                        ? DB::table('product_variant_location_stocks')->where('product_variant_id', $variant->id)->where('location_id', $storeId)->lockForUpdate()->first()
                        : DB::table('location_product')->where('product_id', $source->stockItem->product_id)->where('location_id', $storeId)->lockForUpdate()->first();
                    if (! $stock || (int) round((float) $stock->opening_quantity * 10000) < $consumed * 10000) throw ValidationException::withMessages(['lines' => 'Insufficient stock on the vehicle.']);
                    if ($variant) DB::table('product_variant_location_stocks')->where('id', $stock->id)->decrement('opening_quantity', $consumed);
                    else DB::table('location_product')->where('product_id', $source->stockItem->product_id)->where('location_id', $storeId)->decrement('opening_quantity', $consumed);
                }
                $line->update([
                    'delivered_quantity' => $quantities['delivered'] / 10000,
                    'damaged_quantity' => $quantities['damaged'] / 10000,
                    'missing_quantity' => $quantities['missing'] / 10000,
                    'remarks' => $remarks,
                ]);
            }
            $status = ! $hasDelivered ? 'failed' : ($hasDifference ? 'partial' : 'delivered');
            $record->update(['status' => $status, 'completed_at' => now(), 'receiver_name' => $data['receiver_name'], 'receiver_phone' => $data['receiver_phone'] ?? null, 'proof_notes' => $data['proof_notes'] ?? null]);
            $record->events()->create(['event' => $status, 'user_id' => $userId, 'created_at' => now()]);
        });
    }

    public function arrive(DeliveryConsignment $consignment, int $userId): void
    {
        DB::transaction(function () use ($consignment, $userId) {
            $record = DeliveryConsignment::lockForUpdate()->findOrFail($consignment->id);
            if ($record->status !== DeliveryConsignment::STATUS_IN_TRANSIT || $record->arrived_at) throw ValidationException::withMessages(['status' => 'Only an in-transit delivery can be marked arrived once.']);
            $record->update(['status' => DeliveryConsignment::STATUS_ARRIVED, 'arrived_at' => now()]);
            $record->events()->create(['event' => 'arrived', 'user_id' => $userId, 'created_at' => now()]);
        });
    }

}
