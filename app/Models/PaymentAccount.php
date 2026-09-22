<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    protected $fillable = ['selected_type_id','name','account_number','payment_account_type_id','payment_account_sub_type_id','opening_balance','current_balance','is_active','created_by'];
    protected $with = ['selectedType'];
    protected $appends = ['payment_account_type_id','payment_account_sub_type_id'];
    public function selectedType() { return $this->belongsTo(PaymentAccountType::class,'selected_type_id'); }
    public function getPaymentAccountTypeIdAttribute() { return $this->selectedType?->parent_id ?? $this->attributes['selected_type_id'] ?? null; }
    public function getPaymentAccountSubTypeIdAttribute() { return $this->selectedType?->parent_id ? $this->selected_type_id : null; }
    public function setPaymentAccountTypeIdAttribute($value): void { $this->attributes['selected_type_id']=$value; $this->unsetRelation('selectedType'); }
    public function setPaymentAccountSubTypeIdAttribute($value): void { if ($value) { $this->attributes['selected_type_id']=$value; $this->unsetRelation('selectedType'); } }
    protected function casts(): array { return ['opening_balance'=>'decimal:2','current_balance'=>'decimal:2','is_active'=>'boolean']; }
    public function type() { return $this->belongsTo(PaymentAccountType::class, 'payment_account_type_id'); }
    public function subType() { return $this->belongsTo(PaymentAccountType::class, 'payment_account_sub_type_id'); }
    public function details() { return $this->hasMany(PaymentAccountDetail::class)->orderBy('display_order'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
