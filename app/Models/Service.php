<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'unit_id',
        'unit_cost',
        'unit_rate',
        'cost_ledger_id',
        'revenue_ledger_id',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'unit_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function costLedger(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'cost_ledger_id');
    }

    public function revenueLedger(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'revenue_ledger_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function consumables(): HasMany
    {
        return $this->hasMany(ServiceConsumable::class);
    }

    /**
     * Resolve unit display name.
     */
    public function getDisplayUnitAttribute(): string
    {
        return $this->unit ? ($this->unit->short_name ?: $this->unit->name) : '—';
    }
}
