<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerDocument extends Model { protected $guarded=['id']; public function customer(){return $this->belongsTo(Contact::class,'contact_id');} public function creator(){return $this->belongsTo(User::class,'created_by');} }
