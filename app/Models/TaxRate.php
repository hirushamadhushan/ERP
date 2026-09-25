<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    protected $fillable = ['name', 'amount', 'is_tax_group', 'for_tax_group'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'is_tax_group' => 'boolean', 'for_tax_group' => 'boolean'];
    }

    public function scopeSingles(Builder $query): Builder { return $query->where('is_tax_group', false); }
    public function scopeGroups(Builder $query): Builder { return $query->where('is_tax_group', true); }

    public function scopeAvailableForProducts(Builder $query): Builder
    {
        return $query->where(fn (Builder $taxes) => $taxes->where('is_tax_group', true)->orWhere('for_tax_group', false));
    }

    public function subTaxes(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'group_sub_taxes', 'group_tax_id', 'tax_rate_id');
    }

    public function taxGroups(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'group_sub_taxes', 'tax_rate_id', 'group_tax_id');
    }

    public function products(): HasMany { return $this->hasMany(Product::class); }
}
