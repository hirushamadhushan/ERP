<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceCreditNote extends Model
{
    protected $fillable = ['credit_number', 'credit_date', 'status', 'amount', 'untaxed_amount', 'tax_amount', 'reason', 'created_by'];
    protected function casts(): array { return ['credit_date'=>'date','amount'=>'decimal:2','untaxed_amount'=>'decimal:2','tax_amount'=>'decimal:2']; }
    public function invoice() { return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function lines() { return $this->hasMany(SalesInvoiceCreditNoteLine::class); }
    public function journalItems() { return $this->hasMany(SalesInvoiceCreditNoteJournalItem::class); }
}
