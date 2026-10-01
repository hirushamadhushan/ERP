<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTransferLine extends Model
{
    public $timestamps = false;
    protected $fillable = ['stock_item_id', 'quantity'];
    public function stockItem() { return $this->belongsTo(ProductStockItem::class, 'stock_item_id'); }
    public function lots() { return $this->belongsToMany(ProductLot::class, 'delivery_transfer_line_lots', 'line_id', 'lot_id'); }
    public function serials() { return $this->belongsToMany(ProductSerialNumber::class, 'delivery_transfer_line_serials', 'line_id', 'serial_id'); }
}
