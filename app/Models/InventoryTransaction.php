<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $fillable = ['transaction_type', 'reference', 'notes', 'created_by', 'occurred_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime']; }
    public function movements() { return $this->hasMany(InventoryMovement::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
