<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesInvoicePayment extends Model { protected $guarded=[]; protected function casts():array{return ['payment_date'=>'date','amount'=>'decimal:2'];} public function invoice(){return $this->belongsTo(SalesInvoice::class,'sales_invoice_id');} public function account(){return $this->belongsTo(PaymentAccount::class,'payment_account_id');} public function creator(){return $this->belongsTo(User::class,'created_by');} }
