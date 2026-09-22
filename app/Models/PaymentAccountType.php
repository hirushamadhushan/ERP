<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PaymentAccountType extends Model
{
    protected $fillable = ['name', 'parent_id'];
    public function accounts() { return $this->hasMany(PaymentAccount::class, 'selected_type_id'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('name'); }
}
