<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    public $timestamps = false;
    protected $fillable = ['inventory_transaction_id', 'product_lot_id', 'location_id', 'quantity_delta', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function transaction() { return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id'); }
    public function lot() { return $this->belongsTo(ProductLot::class, 'product_lot_id'); }
    public function location() { return $this->belongsTo(Location::class); }
}
