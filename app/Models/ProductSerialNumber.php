<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSerialNumber extends Model
{
    protected static function booted(): void
    {
        static::saving(function ($record) {
            $record->serial_key = strtolower($record->serial_number);
            if ($record->product_variant_id) {
                $variant=ProductVariant::findOrFail($record->product_variant_id);
                $direct=$record->getAttributes()['product_id'] ?? null;
                if ($direct && (int)$direct !== (int)$variant->product_id) throw \Illuminate\Validation\ValidationException::withMessages(['product_id'=>'The variant does not belong to this product.']);
                $record->setAttribute('product_id',null);
            }
        });
    }

    protected $fillable = ['product_id', 'location_id', 'product_variant_id', 'serial_number'];
    protected $appends = ['variation'];
    protected $with = ['variant'];

    public function getProductIdAttribute($value) { return $value ?? $this->variant?->product_id; }
    public function scopeForProduct($query, $id) {
        return $query->where(fn($q)=>$q->where('product_serial_numbers.product_id',$id)->orWhereHas('variant',fn($v)=>$v->where('product_id',$id)));
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function getVariationAttribute(): string { return $this->variant?->value ?? 'Default'; }
}
