<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LocationReceiptSetting extends Model { protected $fillable=['auto_print_invoice','printer_type','receipt_printer_id','invoice_layout_id','invoice_scheme_id']; protected function casts():array{return ['auto_print_invoice'=>'boolean'];} public function printer(){return $this->belongsTo(ReceiptPrinter::class,'receipt_printer_id');} public function layout(){return $this->belongsTo(InvoiceLayout::class,'invoice_layout_id');} public function scheme(){return $this->belongsTo(InvoiceScheme::class,'invoice_scheme_id');} }
