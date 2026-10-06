<?php
namespace App\Services;

use App\Models\{DeliveryDriver, DeliveryVehicle, Location};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryFleetService
{
    public function saveVehicle(DeliveryVehicle $vehicle, array $data): DeliveryVehicle
    {
        return DB::transaction(function () use ($vehicle, $data) {
            if ($vehicle->exists) $vehicle = DeliveryVehicle::lockForUpdate()->findOrFail($vehicle->id);
            $vehicle->fill($data)->save();
            $store = $vehicle->stores()->first();
            if (! $store) {
                $store = Location::create(['name' => 'Vehicle: '.$vehicle->number, 'code' => 'VEH-'.Str::ulid(), 'is_active' => true]);
                $vehicle->stores()->attach($store->id);
            } else {
                // Keep the store usable for unloading even when a vehicle is inactive.
                $store->update(['name' => 'Vehicle: '.$vehicle->number]);
            }
            return $vehicle;
        });
    }

    public function saveDriver(DeliveryDriver $driver, array $data, int $userId): DeliveryDriver
    {
        return DB::transaction(function () use ($driver, $data, $userId) {
            // Serialize assignments in a stable order; each driver and vehicle has one current assignment.
            $vehicles = DeliveryVehicle::orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($driver->exists) $driver = DeliveryDriver::lockForUpdate()->findOrFail($driver->id);
            $vehicleId = $data['vehicle_id'] ?? null;
            unset($data['vehicle_id']);
            $driver->fill($data)->save();
            if ($vehicleId) {
                if (! $driver->is_active || ! $vehicles->get($vehicleId)?->is_active) {
                    throw ValidationException::withMessages(['vehicle_id' => 'Assign an active driver to an active vehicle.']);
                }
                if (DB::table('delivery_vehicle_assignments')->where('vehicle_id', $vehicleId)->where('driver_id', '!=', $driver->id)->exists()) {
                    throw ValidationException::withMessages(['vehicle_id' => 'This vehicle already has a driver. Remove that assignment first.']);
                }
            }
            $current = DB::table('delivery_vehicle_assignments')->where('driver_id', $driver->id)->first();
            if ((string) ($current?->vehicle_id ?? '') !== (string) ($vehicleId ?? '')) {
                // Current assignments stay compact; immutable events preserve the audit trail.
                if ($current) DB::table('delivery_vehicle_assignment_history')->insert([
                    'driver_id' => $driver->id, 'vehicle_id' => $current->vehicle_id,
                    'action' => 'unassigned', 'changed_by' => $userId, 'created_at' => now(),
                ]);
                $driver->vehicles()->detach();
                if ($vehicleId) {
                    $driver->vehicles()->attach($vehicleId, ['assigned_by' => $userId, 'assigned_at' => now()]);
                    DB::table('delivery_vehicle_assignment_history')->insert([
                        'driver_id' => $driver->id, 'vehicle_id' => $vehicleId,
                        'action' => 'assigned', 'changed_by' => $userId, 'created_at' => now(),
                    ]);
                }
            }
            return $driver;
        }, 3);
    }
}
