<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesInvoiceCreditNoteJournalItem extends Model { protected $guarded=[]; protected function casts():array{return ['debit'=>'decimal:2','credit'=>'decimal:2'];} public function creditNote(){return $this->belongsTo(SalesInvoiceCreditNote::class,'sales_invoice_credit_note_id');} }
