<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    protected $fillable = ['name', 'calculation_type', 'calculation_percentage', 'selling_price_group'];

    protected function casts(): array
    {
        return ['calculation_percentage' => 'decimal:2'];
    }
}
