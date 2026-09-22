<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentAccountDeposit extends Model {
    protected $guarded=['id'];
    protected function casts(): array { return ['amount'=>'decimal:2','deposited_at'=>'datetime']; }
    public function account(){return $this->belongsTo(PaymentAccount::class);}
    public function source(){return $this->belongsTo(PaymentAccount::class,'from_account_id');}
    public function creator(){return $this->belongsTo(User::class,'created_by');}
}
