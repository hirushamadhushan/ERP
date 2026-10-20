<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoice extends Model
{
    protected $fillable = ['invoice_number', 'sales_order_id', 'customer_id', 'location_id', 'commission_agent_id', 'salesperson_id',
        'recipient_bank_account_id', 'invoice_date', 'due_date', 'delivery_date', 'payment_term', 'journal', 'status',
        'tax_display', 'untaxed_amount', 'tax_amount', 'total_amount', 'paid_amount', 'credited_amount', 'balance_due', 'terms', 'customer_reference', 'payment_reference',
        'incoterm', 'incoterm_location', 'fiscal_position', 'payment_method', 'auto_post', 'created_by', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['invoice_date'=>'date', 'due_date'=>'date', 'delivery_date'=>'date', 'posted_at'=>'datetime',
            'auto_post'=>'boolean', 'untaxed_amount'=>'decimal:2', 'tax_amount'=>'decimal:2', 'total_amount'=>'decimal:2', 'paid_amount'=>'decimal:2', 'credited_amount'=>'decimal:2', 'balance_due'=>'decimal:2'];
    }

    public function customer() { return $this->belongsTo(Contact::class, 'customer_id'); }
    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function commissionAgent() { return $this->belongsTo(Contact::class, 'commission_agent_id'); }
    public function salesperson() { return $this->belongsTo(User::class, 'salesperson_id'); }
    public function recipientBankAccount() { return $this->belongsTo(PaymentAccount::class, 'recipient_bank_account_id'); }
    public function lines() { return $this->hasMany(SalesInvoiceLine::class)->orderBy('sort_order'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function poster() { return $this->belongsTo(User::class, 'posted_by'); }
    public function journalItems() { return $this->hasMany(SalesInvoiceJournalItem::class); }
    public function payments() { return $this->hasMany(SalesInvoicePayment::class)->latest('payment_date'); }
    public function creditAllocations() { return $this->hasMany(SalesInvoiceCreditAllocation::class)->latest(); }
    public function creditNotes() { return $this->hasMany(SalesInvoiceCreditNote::class)->latest(); }
    public function activities() { return $this->hasMany(SalesInvoiceActivity::class)->latest(); }
    public function installments() { return $this->hasMany(SalesInvoiceInstallment::class)->orderBy('sequence'); }
}
