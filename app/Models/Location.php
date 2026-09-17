<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'name', 'code', 'landmark', 'city', 'zip_code', 'state', 'country',
        'price_group', 'invoice_scheme', 'invoice_layout_pos', 'invoice_layout_sale', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
