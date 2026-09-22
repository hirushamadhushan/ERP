<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariationTemplateValue extends Model
{
    public $timestamps = false;
    protected $fillable = ['value', 'sort_order'];

    public function template() { return $this->belongsTo(VariationTemplate::class, 'variation_template_id'); }
}
