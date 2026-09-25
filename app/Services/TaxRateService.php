<?php

namespace App\Services;

use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxRateService
{
    public function saveSingle(array $data, ?TaxRate $taxRate = null): TaxRate
    {
        return DB::transaction(function () use ($data, $taxRate) {
            $taxRate = $taxRate?->newQuery()->lockForUpdate()->findOrFail($taxRate->id) ?? new TaxRate;
            $this->ensureType($taxRate, false);
            if (($data['for_tax_group'] ?? false) && $taxRate->exists && $taxRate->products()->exists()) {
                throw ValidationException::withMessages(['for_tax_group' => 'A tax assigned to products cannot be changed to group-only.']);
            }
            $taxRate->fill($data + ['is_tax_group' => false])->save();

            // A child-rate change must leave every cached group total consistent.
            $taxRate->taxGroups()->lockForUpdate()->get()->each(fn (TaxRate $group) => $this->recalculate($group));

            return $taxRate;
        }, 3);
    }

    public function saveGroup(array $data, ?TaxRate $taxGroup = null): TaxRate
    {
        return DB::transaction(function () use ($data, $taxGroup) {
            $taxGroup = $taxGroup?->newQuery()->lockForUpdate()->findOrFail($taxGroup->id) ?? new TaxRate;
            $this->ensureType($taxGroup, true);
            $taxGroup->fill(['name' => $data['name'], 'is_tax_group' => true, 'for_tax_group' => false])->save();
            $taxGroup->subTaxes()->sync($data['tax_rate_ids']);
            $this->recalculate($taxGroup);

            return $taxGroup;
        }, 3);
    }

    public function deleteSingle(TaxRate $taxRate): void
    {
        DB::transaction(function () use ($taxRate) {
            $taxRate = TaxRate::lockForUpdate()->findOrFail($taxRate->id);
            $this->ensureType($taxRate, false);
            if ($taxRate->taxGroups()->exists()) {
                throw ValidationException::withMessages(['tax_rate' => 'This tax rate is used by a tax group and cannot be deleted.']);
            }
            if ($taxRate->products()->exists()) {
                throw ValidationException::withMessages(['tax_rate' => 'This tax rate is assigned to products and cannot be deleted.']);
            }
            $taxRate->delete();
        }, 3);
    }

    public function deleteGroup(TaxRate $taxGroup): void
    {
        DB::transaction(function () use ($taxGroup) {
            $taxGroup = TaxRate::lockForUpdate()->findOrFail($taxGroup->id);
            $this->ensureType($taxGroup, true);
            if ($taxGroup->products()->exists()) {
                throw ValidationException::withMessages(['tax_group' => 'This tax group is assigned to products and cannot be deleted.']);
            }
            $taxGroup->subTaxes()->detach();
            $taxGroup->delete();
        }, 3);
    }

    private function recalculate(TaxRate $group): void
    {
        $total = $group->subTaxes()->sum('tax_rates.amount');
        if ($total > 100) {
            throw ValidationException::withMessages(['tax_rate_ids' => 'The combined tax rate cannot exceed 100%.']);
        }
        $group->update(['amount' => $total]);
    }

    private function ensureType(TaxRate $taxRate, bool $group): void
    {
        if ($taxRate->exists && $taxRate->is_tax_group !== $group) abort(404);
    }
}
