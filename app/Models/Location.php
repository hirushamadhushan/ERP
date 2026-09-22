<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'name', 'code', 'landmark', 'city', 'zip_code', 'state', 'country',
        'price_group', 'invoice_scheme_id', 'invoice_layout_pos_id', 'invoice_layout_sale_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
    public function invoiceScheme() { return $this->belongsTo(InvoiceScheme::class); }
    public function invoiceLayoutPos() { return $this->belongsTo(InvoiceLayout::class, 'invoice_layout_pos_id'); }
    public function invoiceLayoutSale() { return $this->belongsTo(InvoiceLayout::class, 'invoice_layout_sale_id'); }
}
