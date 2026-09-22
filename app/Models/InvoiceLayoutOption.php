<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class InvoiceLayoutOption extends Model { public $timestamps=false; protected $fillable=['key','enabled']; protected function casts():array{return ['enabled'=>'boolean'];} }
