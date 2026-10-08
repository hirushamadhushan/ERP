<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryConsignment extends Model
{
    public const STATUS_LOADED = 'loaded';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const ACTIVE_STATUSES = [self::STATUS_LOADED, self::STATUS_IN_TRANSIT, self::STATUS_ARRIVED];

    protected $fillable = ['number', 'loading_transfer_id', 'customer_id', 'status', 'scheduled_at', 'departed_at', 'arrived_at', 'completed_at', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'receiver_name', 'receiver_phone', 'receiver_id_reference', 'receiver_latitude', 'receiver_longitude', 'delivery_address', 'notes', 'proof_notes', 'created_by'];
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'departed_at' => 'datetime', 'arrived_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function loadingTransfer() { return $this->belongsTo(DeliveryTransfer::class, 'loading_transfer_id'); }
    public function customer() { return $this->belongsTo(Contact::class, 'customer_id'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function lines() { return $this->hasMany(DeliveryConsignmentLine::class, 'consignment_id'); }
    public function events() { return $this->hasMany(DeliveryConsignmentEvent::class, 'consignment_id'); }
    public function proofs() { return $this->hasMany(DeliveryConsignmentProof::class, 'consignment_id'); }
    public function routeStop() { return $this->hasOne(DeliveryRouteStop::class, 'consignment_id'); }
    public function corrections() { return $this->hasMany(DeliveryPodCorrection::class, 'consignment_id'); }
    public function scopeActive($query) { return $query->whereIn('status', self::ACTIVE_STATUSES); }
}
