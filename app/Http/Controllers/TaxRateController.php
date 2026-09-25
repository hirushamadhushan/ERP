<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaxRateRequest;
use App\Models\TaxRate;
use App\Services\TaxRateService;

class TaxRateController extends Controller
{
    public function index()
    {
        return view('tax-rates.index', [
            'taxRates' => TaxRate::singles()->withCount('taxGroups')->orderBy('name')->get(),
            'taxGroups' => TaxRate::groups()->with('subTaxes')->orderBy('name')->get(),
        ]);
    }

    public function store(SaveTaxRateRequest $request, TaxRateService $service)
    {
        $service->saveSingle($request->validated());
        return back()->with('success', 'Tax rate added successfully.');
    }

    public function update(SaveTaxRateRequest $request, TaxRate $taxRate, TaxRateService $service)
    {
        $service->saveSingle($request->validated(), $taxRate);
        return back()->with('success', 'Tax rate updated successfully. Related group totals were recalculated.');
    }

    public function destroy(TaxRate $taxRate, TaxRateService $service)
    {
        $service->deleteSingle($taxRate);
        return back()->with('success', 'Tax rate deleted successfully.');
    }
}
