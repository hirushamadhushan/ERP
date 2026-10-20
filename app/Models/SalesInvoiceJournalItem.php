<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesInvoiceJournalItem extends Model { protected $guarded=[]; protected function casts():array{return ['debit'=>'decimal:2','credit'=>'decimal:2'];} public function invoice(){return $this->belongsTo(SalesInvoice::class,'sales_invoice_id');} }
