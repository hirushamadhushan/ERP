<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesInvoiceActivity extends Model { protected $guarded=[]; public function invoice(){return $this->belongsTo(SalesInvoice::class,'sales_invoice_id');} public function creator(){return $this->belongsTo(User::class,'created_by');} }
