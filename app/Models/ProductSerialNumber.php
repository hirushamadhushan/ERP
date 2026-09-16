<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSerialNumber extends Model
{
    protected static function booted(): void
    {
        static::saving(function ($record) {
            $record->serial_key = strtolower($record->serial_number);
        });
    }

    protected $fillable = ['product_id', 'location_id', 'variation', 'serial_number'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
