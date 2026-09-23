<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'name', 'code', 'location_code', 'landmark', 'city', 'zip_code', 'state', 'country',
        'price_group', 'invoice_scheme_id', 'invoice_layout_pos_id', 'invoice_layout_sale_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
    public function invoiceScheme() { return $this->belongsTo(InvoiceScheme::class); }
    public function invoiceLayoutPos() { return $this->belongsTo(InvoiceLayout::class, 'invoice_layout_pos_id'); }
    public function invoiceLayoutSale() { return $this->belongsTo(InvoiceLayout::class, 'invoice_layout_sale_id'); }
    public function contacts() { return $this->hasMany(LocationContact::class); }
    public function customFieldValues() { return $this->hasMany(LocationCustomFieldValue::class); }
    public function featuredProducts() { return $this->belongsToMany(Product::class, 'location_featured_products')->withPivot('display_order')->orderBy('location_featured_products.display_order'); }
    public function paymentMethods() { return $this->hasMany(LocationPaymentMethod::class); }
    public function receiptSetting() { return $this->hasOne(LocationReceiptSetting::class); }
}
