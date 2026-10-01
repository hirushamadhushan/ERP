<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLot extends Model
{
    protected $fillable = [
        'product_stock_item_id', 'lot_number', 'supplier_lot_code',
        'manufactured_at', 'expires_at', 'unit_cost', 'selling_price', 'created_by',
    ];

    protected function casts(): array
    {
        return ['manufactured_at' => 'date', 'expires_at' => 'date'];
    }

    public function stockItem() { return $this->belongsTo(ProductStockItem::class, 'product_stock_item_id'); }
    public function movements() { return $this->hasMany(InventoryMovement::class); }
}
