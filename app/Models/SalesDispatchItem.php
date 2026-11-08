<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesDispatchItem extends Model
{
    protected $table = 'sales_dispatch_items';

    protected $fillable = [
        'sales_dispatch_id',
        'product_id',
        'item_code',
        'item_name',
        'inv_qty',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'inv_qty' => 'decimal:4',
            'qty' => 'decimal:4',
        ];
    }

    public function dispatch()
    {
        return $this->belongsTo(SalesDispatch::class, 'sales_dispatch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
