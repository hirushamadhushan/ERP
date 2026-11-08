<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model {
    protected $fillable = [
        'order_number',
        'title',
        'customer_id',
        'location_id',
        'salesperson_id',
        'commission_agent_id',
        'order_date',
        'valid_until',
        'delivery_date',
        'status',
        'reference',
        'total_amount',
        'discount_percentage',
        'discount_amount',
        'net_total',
        'terms',
        'payment_terms',
        'created_by'
    ];

    protected function casts(): array {
        return [
            'order_date' => 'date',
            'valid_until' => 'date',
            'delivery_date' => 'date',
            'total_amount' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_total' => 'decimal:2',
        ];
    }

    public function customer() {
        return $this->belongsTo(Contact::class);
    }

    public function salesperson() {
        return $this->belongsTo(User::class);
    }

    public function lines() {
        return $this->hasMany(SalesOrderLine::class)->orderBy('sort_order');
    }
}
