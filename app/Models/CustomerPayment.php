<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerPayment extends Model { protected $guarded=['id']; protected function casts(): array{return ['amount'=>'decimal:2','paid_at'=>'datetime'];} public function customer(){return $this->belongsTo(Contact::class,'contact_id');} public function creator(){return $this->belongsTo(User::class,'created_by');} }
