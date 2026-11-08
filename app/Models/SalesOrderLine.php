<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderLine extends Model {
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'service_id',
        'product_lot_id',
        'tax_rate_id',
        'item_type',
        'description',
        'available_qty',
        'quantity',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'line_total',
        'sort_order'
    ];

    protected function casts(): array {
        return [
            'available_qty' => 'decimal:4',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function service() {
        return $this->belongsTo(Service::class);
    }

    public function productLot() {
        return $this->belongsTo(ProductLot::class);
    }
}
