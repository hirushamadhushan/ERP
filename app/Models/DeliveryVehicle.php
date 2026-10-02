<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryVehicle extends Model
{
    protected $fillable = ['number', 'name', 'brand', 'model', 'year', 'fuel_type', 'chassis_number', 'engine_number', 'insurance_expires_at', 'revenue_license_expires_at', 'notes', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function stores() { return $this->belongsToMany(Location::class, 'delivery_vehicle_stores', 'vehicle_id', 'location_id'); }
    public function drivers() { return $this->belongsToMany(DeliveryDriver::class, 'delivery_vehicle_assignments', 'vehicle_id', 'driver_id')->withPivot('assigned_at', 'assigned_by'); }
}
