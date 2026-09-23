<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LocationPaymentMethod extends Model { protected $fillable=['method','is_enabled','payment_account_id']; protected function casts():array{return ['is_enabled'=>'boolean'];} public function paymentAccount(){return $this->belongsTo(PaymentAccount::class);} }
