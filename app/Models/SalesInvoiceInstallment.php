<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceInstallment extends Model
{
    protected $fillable=['sequence','due_date','amount','settled_amount','status'];
    protected function casts():array{return ['due_date'=>'date','amount'=>'decimal:2','settled_amount'=>'decimal:2'];}
    public function invoice(){return $this->belongsTo(SalesInvoice::class,'sales_invoice_id');}
}
