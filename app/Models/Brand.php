<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'use_for_repair'];

    protected $casts = [
        'use_for_repair' => 'boolean',
    ];

    /**
     * Return active brands in the order used by product forms and filters.
     */
    public static function forDropdown(bool $showNone = false, bool $repairOnly = false): Collection
    {
        $brands = static::query()
            ->when($repairOnly, fn ($query) => $query->where('use_for_repair', true))
            ->orderBy('name')
            ->pluck('name', 'id');

        return $showNone ? collect(['' => 'None'])->merge($brands) : $brands;
    }
}
