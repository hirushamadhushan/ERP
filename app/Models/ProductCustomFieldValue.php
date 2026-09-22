<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductCustomFieldValue extends Model {
    public $timestamps=false;
    public $incrementing=false;
    protected $fillable=['field_key','value','display_order'];
}
