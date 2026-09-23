<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReceiptPrinter extends Model { protected $fillable=['name','connection_type','connection_value','is_active']; protected function casts():array{return ['is_active'=>'boolean'];} }
