<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceCreditAllocation extends Model
{
    protected $fillable = ['amount', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function invoice() { return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
