<?php

namespace App\Services;

use App\Models\{Contact, DeliveryConsignment, DeliveryPodCorrection, DeliveryTransfer, DeliveryVehicle, InventoryMovement, InventoryTransaction, Location, Product, ProductSerialNumber};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryConsignmentService
{
    public function create(array $data, int $userId): DeliveryConsignment
    {
        return DB::transaction(function () use ($data, $userId) {
            $transfer = DeliveryTransfer::with(['vehicle.stores', 'lines.stockItem.variant', 'lines.lots', 'lines.serials'])
                ->lockForUpdate()->findOrFail($data['loading_transfer_id']);
            if ($transfer->direction !== 'loading' || DeliveryConsignment::where('loading_transfer_id', $transfer->id)->where('status', '!=', DeliveryConsignment::STATUS_CANCELLED)->exists()) {
                throw ValidationException::withMessages(['loading_transfer_id' => 'Choose an unused vehicle loading transfer.']);
            }
            // The vehicle lock serializes delivery creation and stock-transfer checks.
            DeliveryVehicle::lockForUpdate()->findOrFail($transfer->vehicle_id);
            if (DeliveryConsignment::whereIn('status', [DeliveryConsignment::STATUS_IN_TRANSIT, DeliveryConsignment::STATUS_ARRIVED])->whereHas('loadingTransfer', fn ($query) => $query->where('vehicle_id', $transfer->vehicle_id))->exists()) {
                throw ValidationException::withMessages(['loading_transfer_id' => 'This vehicle has already departed. Complete its current route before creating another delivery.']);
            }
            if (! $transfer->hasStockAvailableForConsignment()) {
                throw ValidationException::withMessages(['loading_transfer_id' => 'This loading record is no longer available because some or all of its stock has left the vehicle. Load the required stock again first.']);
            }
            $customer = Contact::findOrFail($data['customer_id']);
            if (! in_array($customer->type, ['customer', 'both'], true) || $customer->status !== 'active') throw ValidationException::withMessages(['customer_id' => 'Choose an active customer.']);
            $consignment = DeliveryConsignment::create([
                // The number column is 20 characters. Use a collision-resistant,
                // fixed-width placeholder until the auto-increment ID is available.
                'number' => 'TMP-'.bin2hex(random_bytes(8)),
                'loading_transfer_id' => $transfer->id,
                'customer_id' => $customer->id,
                'delivery_address' => $data['delivery_address'],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
            $consignment->update(['number' => 'DN-'.str_pad((string) $consignment->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($transfer->lines as $line) {
                $consignmentLine = $consignment->lines()->create(['transfer_line_id' => $line->id]);
                foreach ($line->serials as $serial) $consignmentLine->serialOutcomes()->create(['serial_id' => $serial->id]);
            }
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
            $this->syncRoute($record);
        });
    }

    public function complete(DeliveryConsignment $consignment, array $data, int $userId): void
    {
        DB::transaction(function () use ($consignment, $data, $userId) {
            $record = DeliveryConsignment::with(['loadingTransfer.vehicle.stores', 'lines.transferLine.stockItem.variant', 'lines.transferLine.stockItem.product', 'lines.transferLine.lots', 'lines.transferLine.serials', 'lines.serialOutcomes'])
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
                $serialInputs = collect();
                if ($line->transferLine->serials->isNotEmpty()) {
                    $serialInputs = collect($input['serial_outcomes'] ?? [])->keyBy('serial_id');
                    $expectedIds = $line->transferLine->serials->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
                    $submittedIds = $serialInputs->keys()->map(fn ($id) => (int) $id)->sort()->values();
                    if ($expectedIds->all() !== $submittedIds->all()) throw ValidationException::withMessages(['lines' => 'Choose one outcome for every loaded serial number.']);
                    foreach (array_keys($quantities) as $key) $quantities[$key] = $serialInputs->where('outcome', $key)->count() * 10000;
                }
                if (collect($quantities)->contains(fn ($quantity) => $quantity < 0)) throw ValidationException::withMessages(['lines' => 'Outcome quantities cannot be negative.']);
                $loaded = (int) round((float) $line->transferLine->quantity * 10000);
                if (array_sum($quantities) !== $loaded) throw ValidationException::withMessages(['lines' => 'Delivered, damaged and missing quantities must equal the loaded quantity for every line.']);
                $product = $line->transferLine->stockItem->product;
                if (! $product->unit?->allow_decimal && collect($quantities)->contains(fn ($quantity) => $quantity % 10000 !== 0)) throw ValidationException::withMessages(['lines' => 'Use whole numbers for this product.']);
                $hasDelivered = $hasDelivered || $quantities['delivered'] > 0;
                $hasDifference = $hasDifference || $quantities['damaged'] > 0 || $quantities['missing'] > 0;
                $results[] = [$line, $quantities, $input['remarks'] ?? null, $serialInputs];
            }

            $transaction = InventoryTransaction::create(['transaction_type' => 'vehicle_delivery', 'reference' => $record->number, 'notes' => $data['proof_notes'] ?? null, 'created_by' => $userId, 'occurred_at' => now()]);
            $quarantineId = collect($results)->contains(fn($result) => $result[1]['damaged'] > 0) ? $this->quarantineLocation($record->loadingTransfer->warehouse_id) : null;
            foreach ($results as [$line, $quantities, $remarks, $serialInputs]) {
                $source = $line->transferLine;
                $product = $source->stockItem->product;
                $consumed = ($quantities['delivered'] + $quantities['damaged'] + $quantities['missing']) / 10000;
                if ($consumed > 0 && $source->lots->isNotEmpty()) {
                    $lot = $source->lots->first();
                    $available = InventoryMovement::where('product_lot_id', $lot->id)->where('location_id', $storeId)->sum('quantity_delta');
                    if ((int) round((float) $available * 10000) < $consumed * 10000) throw ValidationException::withMessages(['lines' => 'Insufficient lot stock on the vehicle.']);
                    InventoryMovement::create(['inventory_transaction_id' => $transaction->id, 'product_lot_id' => $lot->id, 'location_id' => $storeId, 'quantity_delta' => -$consumed, 'created_at' => now()]);
                    if ($quantities['damaged'] > 0) {
                        $product->locations()->syncWithoutDetaching([$quarantineId]);
                        InventoryMovement::create(['inventory_transaction_id'=>$transaction->id,'product_lot_id'=>$lot->id,'location_id'=>$quarantineId,'quantity_delta'=>$quantities['damaged']/10000,'created_at'=>now()]);
                    }
                }
                if ($consumed > 0 && $source->serials->isNotEmpty()) {
                    $serials = ProductSerialNumber::whereIn('id', $source->serials->pluck('id'))->lockForUpdate()->get();
                    if ($serials->count() !== (int) $consumed || $serials->contains(fn ($serial) => $serial->location_id !== $storeId || $serial->status !== 'available')) throw ValidationException::withMessages(['lines' => 'The loaded serial is no longer available on this vehicle.']);
                    foreach ($serials as $serial) {
                        $serialInput = $serialInputs->get($serial->id);
                        if ($serialInput['outcome'] === 'delivered') $serial->markSold($transaction->id);
                        elseif ($serialInput['outcome'] === 'damaged') { $product->locations()->syncWithoutDetaching([$quarantineId]); $serial->update(['location_id'=>$quarantineId]); $serial->markDamaged(); }
                        else $serial->markMissing();
                        $line->serialOutcomes->firstWhere('serial_id', $serial->id)?->update([
                            'outcome' => $serialInput['outcome'], 'remarks' => $serialInput['remarks'] ?? null,
                        ]);
                    }
                } elseif ($consumed > 0 && $source->lots->isEmpty()) {
                    $variant = $source->stockItem->variant->first();
                    $stock = $variant
                        ? DB::table('product_variant_location_stocks')->where('product_variant_id', $variant->id)->where('location_id', $storeId)->lockForUpdate()->first()
                        : DB::table('location_product')->where('product_id', $source->stockItem->product_id)->where('location_id', $storeId)->lockForUpdate()->first();
                    if (! $stock || (int) round((float) $stock->opening_quantity * 10000) < $consumed * 10000) throw ValidationException::withMessages(['lines' => 'Insufficient stock on the vehicle.']);
                    if ($variant) DB::table('product_variant_location_stocks')->where('id', $stock->id)->decrement('opening_quantity', $consumed);
                    else DB::table('location_product')->where('product_id', $source->stockItem->product_id)->where('location_id', $storeId)->decrement('opening_quantity', $consumed);
                    if ($quantities['damaged'] > 0) {
                        $damaged = $quantities['damaged']/10000;
                        $product->locations()->syncWithoutDetaching([$quarantineId]);
                        if ($variant) $variant->locationStocks()->firstOrCreate(['location_id'=>$quarantineId],['opening_quantity'=>0])->increment('opening_quantity',$damaged);
                        else DB::table('location_product')->where('product_id',$source->stockItem->product_id)->where('location_id',$quarantineId)->increment('opening_quantity',$damaged);
                    }
                }
                $line->update([
                    'delivered_quantity' => $quantities['delivered'] / 10000,
                    'damaged_quantity' => $quantities['damaged'] / 10000,
                    'missing_quantity' => $quantities['missing'] / 10000,
                    'remarks' => $remarks,
                ]);
                if ($quantities['damaged'] > 0) $line->damageDisposition()->create(['quarantine_location_id'=>$quarantineId,'inventory_transaction_id'=>$transaction->id,'quantity'=>$quantities['damaged']/10000,'status'=>'quarantined']);
            }
            $status = ! $hasDelivered ? 'failed' : ($hasDifference ? 'partial' : 'delivered');
            $record->update(['status' => $status, 'completed_at' => now(), 'receiver_name' => $data['receiver_name'], 'receiver_phone' => $data['receiver_phone'] ?? null, 'receiver_id_reference'=>$data['receiver_id_reference']??null,'receiver_latitude'=>$data['receiver_latitude']??null,'receiver_longitude'=>$data['receiver_longitude']??null,'proof_notes' => $data['proof_notes'] ?? null]);
            $record->events()->create(['event' => $status, 'user_id' => $userId, 'created_at' => now()]);
            $this->syncRoute($record);
        });
    }

    public function arrive(DeliveryConsignment $consignment, int $userId): void
    {
        DB::transaction(function () use ($consignment, $userId) {
            $record = DeliveryConsignment::lockForUpdate()->findOrFail($consignment->id);
            if ($record->status !== DeliveryConsignment::STATUS_IN_TRANSIT || $record->arrived_at) throw ValidationException::withMessages(['status' => 'Only an in-transit delivery can be marked arrived once.']);
            $record->update(['status' => DeliveryConsignment::STATUS_ARRIVED, 'arrived_at' => now()]);
            $record->events()->create(['event' => 'arrived', 'user_id' => $userId, 'created_at' => now()]);
            $this->syncRoute($record);
        });
    }

    public function cancel(DeliveryConsignment $consignment, string $reason, int $userId): void
    {
        DB::transaction(function () use ($consignment, $reason, $userId) {
            $record = DeliveryConsignment::lockForUpdate()->findOrFail($consignment->id);
            if (! in_array($record->status, DeliveryConsignment::ACTIVE_STATUSES, true)) throw ValidationException::withMessages(['cancel_reason' => 'Only an active delivery can be cancelled.']);
            $record->update(['status' => DeliveryConsignment::STATUS_CANCELLED, 'cancel_reason' => $reason, 'cancelled_by' => $userId, 'cancelled_at' => now()]);
            $record->events()->create(['event' => 'cancelled', 'user_id' => $userId, 'created_at' => now()]);
            $this->syncRoute($record);
        });
    }

    public function reschedule(DeliveryConsignment $consignment, string $scheduledAt, string $reason, int $userId): void
    {
        DB::transaction(function () use ($consignment, $scheduledAt, $reason, $userId) {
            $record = DeliveryConsignment::lockForUpdate()->findOrFail($consignment->id);
            if ($record->status !== DeliveryConsignment::STATUS_LOADED) throw ValidationException::withMessages(['scheduled_at' => 'Only a loaded delivery can be rescheduled.']);
            $record->update(['scheduled_at' => $scheduledAt]);
            $record->events()->create(['event' => 'rescheduled', 'user_id' => $userId, 'created_at' => now()]);
            $record->update(['notes' => trim(($record->notes ? $record->notes."\n" : '').'Reschedule reason: '.$reason)]);
        });
    }

    private function quarantineLocation(int $warehouseId): int
    {
        $existing=DB::table('delivery_quarantine_locations')->where('warehouse_id',$warehouseId)->value('location_id');
        if ($existing) return (int)$existing;
        $warehouse=Location::findOrFail($warehouseId);
        $location=Location::firstOrCreate(['code'=>'Q-'.$warehouseId],['name'=>$warehouse->name.' - Damaged / Quarantine','is_active'=>true]);
        DB::table('delivery_quarantine_locations')->insertOrIgnore(['warehouse_id'=>$warehouseId,'location_id'=>$location->id]);
        return $location->id;
    }

    public function requestCorrection(DeliveryConsignment $consignment, string $reason, int $userId): DeliveryPodCorrection
    {
        return DB::transaction(function () use ($consignment,$reason,$userId) {
            $record=DeliveryConsignment::with(['lines.serialOutcomes'])->lockForUpdate()->findOrFail($consignment->id);
            if (!in_array($record->status,[DeliveryConsignment::STATUS_DELIVERED,DeliveryConsignment::STATUS_PARTIAL,DeliveryConsignment::STATUS_FAILED],true)) throw ValidationException::withMessages(['reason'=>'Only a completed POD can be corrected.']);
            if ($record->corrections()->where('status','pending')->exists()) throw ValidationException::withMessages(['reason'=>'A correction request is already awaiting approval.']);
            $snapshot=['status'=>$record->status,'receiver_name'=>$record->receiver_name,'receiver_phone'=>$record->receiver_phone,'receiver_id_reference'=>$record->receiver_id_reference,'receiver_latitude'=>$record->receiver_latitude,'receiver_longitude'=>$record->receiver_longitude,'proof_notes'=>$record->proof_notes,'lines'=>$record->lines->map(fn($line)=>['id'=>$line->id,'delivered'=>$line->delivered_quantity,'damaged'=>$line->damaged_quantity,'missing'=>$line->missing_quantity,'remarks'=>$line->remarks,'serials'=>$line->serialOutcomes->map(fn($outcome)=>['serial_id'=>$outcome->serial_id,'outcome'=>$outcome->outcome,'remarks'=>$outcome->remarks])->values()->all()])->values()->all()];
            $correction=$record->corrections()->create(['status'=>'pending','reason'=>$reason,'original_outcome'=>$snapshot,'requested_by'=>$userId]);
            $record->events()->create(['event'=>'correction_requested','user_id'=>$userId,'created_at'=>now()]);
            return $correction;
        });
    }

    public function approveCorrection(DeliveryPodCorrection $correction, int $userId): void
    {
        DB::transaction(function () use ($correction,$userId) {
            $request=DeliveryPodCorrection::lockForUpdate()->findOrFail($correction->id);
            if ($request->status!=='pending') throw ValidationException::withMessages(['correction'=>'Only a pending correction can be approved.']);
            if ((int)$request->requested_by===$userId) throw ValidationException::withMessages(['correction'=>'A different supervisor must approve this correction.']);
            $record=DeliveryConsignment::with(['loadingTransfer.vehicle.stores','lines.damageDisposition','lines.transferLine.stockItem.product','lines.transferLine.stockItem.variant','lines.transferLine.lots','lines.transferLine.serials','lines.serialOutcomes'])->lockForUpdate()->findOrFail($request->consignment_id);
            if (!in_array($record->status,[DeliveryConsignment::STATUS_DELIVERED,DeliveryConsignment::STATUS_PARTIAL,DeliveryConsignment::STATUS_FAILED],true)) throw ValidationException::withMessages(['correction'=>'This POD is no longer completed.']);
            $storeId=$record->loadingTransfer->vehicle->stores->firstOrFail()->id;
            $transaction=InventoryTransaction::create(['transaction_type'=>'vehicle_delivery_reversal','reference'=>$record->number,'notes'=>'Approved POD correction: '.$request->reason,'created_by'=>$userId,'occurred_at'=>now()]);
            foreach ($record->lines as $line) {
                $source=$line->transferLine; $product=$source->stockItem->product;
                $delivered=(float)$line->delivered_quantity; $damaged=(float)$line->damaged_quantity; $missing=(float)$line->missing_quantity; $total=$delivered+$damaged+$missing;
                if ($source->lots->isNotEmpty()) {
                    $lot=$source->lots->first();
                    if ($total>0) InventoryMovement::create(['inventory_transaction_id'=>$transaction->id,'product_lot_id'=>$lot->id,'location_id'=>$storeId,'quantity_delta'=>$total,'created_at'=>now()]);
                    if ($damaged>0) InventoryMovement::create(['inventory_transaction_id'=>$transaction->id,'product_lot_id'=>$lot->id,'location_id'=>$line->damageDisposition->quarantine_location_id,'quantity_delta'=>-$damaged,'created_at'=>now()]);
                }
                if ($source->serials->isNotEmpty()) {
                    foreach (ProductSerialNumber::whereIn('id',$source->serials->pluck('id'))->lockForUpdate()->get() as $serial) {
                        $serial->forceFill(['location_id'=>$storeId,'status'=>ProductSerialNumber::AVAILABLE,'sold_transaction_id'=>null,'sold_sell_line_id'=>null,'sold_at'=>null])->save();
                    }
                } elseif ($source->lots->isEmpty()) {
                    $variant=$source->stockItem->variant->first();
                    if ($variant) $variant->locationStocks()->firstOrCreate(['location_id'=>$storeId],['opening_quantity'=>0])->increment('opening_quantity',$total);
                    else { $product->locations()->syncWithoutDetaching([$storeId]); DB::table('location_product')->where('product_id',$product->id)->where('location_id',$storeId)->increment('opening_quantity',$total); }
                    if ($damaged>0) {
                        $quarantineId=$line->damageDisposition->quarantine_location_id;
                        if ($variant) $variant->locationStocks()->where('location_id',$quarantineId)->decrement('opening_quantity',$damaged);
                        else DB::table('location_product')->where('product_id',$product->id)->where('location_id',$quarantineId)->decrement('opening_quantity',$damaged);
                    }
                }
                $line->damageDisposition?->delete();
                $line->update(['delivered_quantity'=>0,'damaged_quantity'=>0,'missing_quantity'=>0,'remarks'=>null]);
                $line->serialOutcomes()->update(['outcome'=>null,'remarks'=>null]);
            }
            $record->proofs()->whereNull('deleted_at')->update(['deleted_at'=>now(),'deleted_by'=>$userId]);
            $record->update(['status'=>DeliveryConsignment::STATUS_ARRIVED,'completed_at'=>null,'receiver_name'=>null,'receiver_phone'=>null,'receiver_id_reference'=>null,'receiver_latitude'=>null,'receiver_longitude'=>null,'proof_notes'=>null]);
            $request->update(['status'=>'approved','approved_by'=>$userId,'approved_at'=>now(),'reversal_transaction_id'=>$transaction->id]);
            $record->events()->create(['event'=>'pod_reopened','user_id'=>$userId,'created_at'=>now()]);
            $this->syncRoute($record);
        });
    }

    private function syncRoute(DeliveryConsignment $consignment): void
    {
        $stop=$consignment->routeStop()->with('route.stops.consignment')->first();
        if (!$stop) return;
        $stop->update(['status'=>$consignment->status]);
        $statuses=$stop->route->stops->map(fn($routeStop)=>$routeStop->consignment->status);
        $routeStatus=$statuses->every(fn($status)=>in_array($status,[DeliveryConsignment::STATUS_DELIVERED,DeliveryConsignment::STATUS_PARTIAL,DeliveryConsignment::STATUS_FAILED,DeliveryConsignment::STATUS_CANCELLED],true))?'completed':($statuses->contains(fn($status)=>in_array($status,[DeliveryConsignment::STATUS_IN_TRANSIT,DeliveryConsignment::STATUS_ARRIVED],true))?'in_transit':'planned');
        $stop->route->update(['status'=>$routeStatus]);
    }

}
