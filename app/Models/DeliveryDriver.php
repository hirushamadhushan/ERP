<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryDriver extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'identity_number', 'license_number', 'license_expires_at', 'address', 'emergency_contact', 'notes', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function vehicles() { return $this->belongsToMany(DeliveryVehicle::class, 'delivery_vehicle_assignments', 'driver_id', 'vehicle_id'); }
}
