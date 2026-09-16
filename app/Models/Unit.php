<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends Model
{
    protected $fillable = ['name', 'short_name', 'allow_decimal', 'base_unit_id', 'base_unit_multiplier'];

    protected function casts(): array
    {
        return ['allow_decimal' => 'boolean', 'base_unit_multiplier' => 'decimal:6'];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }
}
