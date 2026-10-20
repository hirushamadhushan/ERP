<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceLine extends Model
{
    protected $fillable = ['line_type', 'product_id', 'product_variant_id', 'product_lot_id', 'tax_rate_id', 'description', 'quantity',
        'unit_price', 'discount_percent', 'discount_amount', 'tax_rate', 'subtotal', 'tax_amount', 'total', 'sort_order'];

    public function invoice() { return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function productLot() { return $this->belongsTo(ProductLot::class); }
    public function taxRate() { return $this->belongsTo(TaxRate::class); }
}
