<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentAccountDetail extends Model
{
    protected $fillable = ['label','value','display_order'];
    public function account() { return $this->belongsTo(PaymentAccount::class, 'payment_account_id'); }
}
