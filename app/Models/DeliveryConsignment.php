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
    public const ACTIVE_STATUSES = [self::STATUS_LOADED, self::STATUS_IN_TRANSIT, self::STATUS_ARRIVED];

    protected $fillable = ['number', 'loading_transfer_id', 'customer_id', 'sales_order_reference', 'status', 'scheduled_at', 'departed_at', 'arrived_at', 'completed_at', 'receiver_name', 'receiver_phone', 'delivery_address', 'notes', 'proof_notes', 'created_by'];
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'departed_at' => 'datetime', 'arrived_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function loadingTransfer() { return $this->belongsTo(DeliveryTransfer::class, 'loading_transfer_id'); }
    public function customer() { return $this->belongsTo(Contact::class, 'customer_id'); }
    public function lines() { return $this->hasMany(DeliveryConsignmentLine::class, 'consignment_id'); }
    public function events() { return $this->hasMany(DeliveryConsignmentEvent::class, 'consignment_id'); }
    public function proofs() { return $this->hasMany(DeliveryConsignmentProof::class, 'consignment_id'); }
    public function returnNote() { return $this->hasOne(DeliveryReturn::class, 'consignment_id'); }
    public function scopeActive($query) { return $query->whereIn('status', self::ACTIVE_STATUSES); }
}
