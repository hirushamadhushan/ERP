<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessProductSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return collect(['expiry_enabled','enable_brands','enable_categories','enable_subcategories','enable_price_tax','enable_our_price','enable_sub_units','enable_racks','enable_row','enable_position','enable_warranty','enable_secondary_unit','enable_serial_numbers'])->mapWithKeys(fn ($key) => [$key => 'boolean'])->all();
    }

    public function businessSetting() { return $this->belongsTo(BusinessSetting::class); }
    public function defaultUnit() { return $this->belongsTo(Unit::class, 'default_unit_id'); }
}
