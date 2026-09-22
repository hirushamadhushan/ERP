<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentAccountTransfer extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount'=>'decimal:2','transferred_at'=>'datetime']; }
    public function source() { return $this->belongsTo(PaymentAccount::class,'from_account_id'); }
    public function destination() { return $this->belongsTo(PaymentAccount::class,'to_account_id'); }
    public function creator() { return $this->belongsTo(User::class,'created_by'); }
}
