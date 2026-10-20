<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesInvoiceCreditNoteLine extends Model { protected $guarded=[]; protected function casts():array{return ['quantity'=>'decimal:4','unit_price'=>'decimal:4','tax_rate'=>'decimal:3','subtotal'=>'decimal:2','tax_amount'=>'decimal:2','total'=>'decimal:2'];} public function creditNote(){return $this->belongsTo(SalesInvoiceCreditNote::class,'sales_invoice_credit_note_id');} public function invoiceLine(){return $this->belongsTo(SalesInvoiceLine::class,'sales_invoice_line_id');} }
