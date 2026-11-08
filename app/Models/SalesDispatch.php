<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesDispatch extends Model
{
    protected $table = 'sales_dispatches';

    protected $fillable = [
        'dispatch_number',
        'sales_order_id',
        'order_number',
        'sales_invoice_id',
        'invoice_number',
        'invoice_date',
        'salesperson_id',
        'salesperson_name',
        'customer_id',
        'customer_name',
        'delivery_vehicle_id',
        'vehicle_number',
        'dispatch_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date',
            'invoice_date' => 'date',
        ];
    }

    public function items()
    {
        return $this->hasMany(SalesDispatchItem::class, 'sales_dispatch_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function customer()
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(DeliveryVehicle::class, 'delivery_vehicle_id');
    }
}
