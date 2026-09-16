<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUnitRequest;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    public function index()
    {
        return view('units.index', ['units' => Unit::with('baseUnit')->orderBy('name')->get()]);
    }

    public function store(SaveUnitRequest $request)
    {
        $this->save($request);

        return redirect()->route('products.units.index')->with('success', 'Unit added successfully.');
    }

    public function update(SaveUnitRequest $request, Unit $unit)
    {
        $this->save($request, $unit);

        return redirect()->route('products.units.index')->with('success', 'Unit updated successfully.');
    }

    public function destroy(Unit $unit)
    {
        return $this->databaseTransaction(function () use ($unit) {
            $unit = Unit::lockForUpdate()->findOrFail($unit->id);
            if (Schema::hasColumn('products', 'unit_id') && Product::where('unit_id', $unit->id)->exists()) {
                return back()->with('unit_error', 'This unit is used by products. Change their unit before deleting it.');
            }
            if (Unit::where('base_unit_id', $unit->id)->exists()) {
                return back()->with('unit_error', 'This unit is used as a base by other units. Update those units before deleting it.');
            }
            $unit->delete();

            return redirect()->route('products.units.index')->with('success', 'Unit deleted successfully.');
        }, 'This unit is still linked to existing products or units.');
    }

    private function save(SaveUnitRequest $request, ?Unit $unit = null): void
    {
        $data = $request->unitData();
        $this->databaseTransaction(function () use ($unit, $data) {
            // Lock related units so concurrent edits cannot create circular multiples.
            if ($unit) {
                $unit = Unit::lockForUpdate()->findOrFail($unit->id);
                if ((bool) $data['allow_decimal'] !== $unit->allow_decimal
                    && Schema::hasColumn('products', 'unit_id')
                    && Product::where('unit_id', $unit->id)->exists()) {
                    throw ValidationException::withMessages(['allow_decimal' => 'This unit is used by products. Keep its decimal setting unchanged.']);
                }
            }
            if ($data['base_unit_id'] !== null) {
                $base = Unit::lockForUpdate()->find($data['base_unit_id']);
                if (! $base || $base->base_unit_id !== null || $base->id === $unit?->id) {
                    throw ValidationException::withMessages(['base_unit_id' => 'Select a different base unit that is not itself a multiple.']);
                }
                if ($unit && Unit::where('base_unit_id', $unit->id)->exists()) {
                    throw ValidationException::withMessages(['base_unit_id' => 'A unit used as a base by other units cannot become a multiple.']);
                }
            }
            if ($unit) {
                $unit->update($data);
            } else {
                Unit::create($data);
            }
        }, 'The unit name or short name is already in use.', 'name');
    }
}
