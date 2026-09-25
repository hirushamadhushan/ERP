<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaxGroupRequest;
use App\Models\TaxRate;
use App\Services\TaxRateService;

class GroupTaxController extends Controller
{
    public function store(SaveTaxGroupRequest $request, TaxRateService $service)
    {
        $service->saveGroup($request->validated());
        return back()->with('success', 'Tax group added successfully.');
    }

    public function update(SaveTaxGroupRequest $request, TaxRate $taxGroup, TaxRateService $service)
    {
        $service->saveGroup($request->validated(), $taxGroup);
        return back()->with('success', 'Tax group updated successfully.');
    }

    public function destroy(TaxRate $taxGroup, TaxRateService $service)
    {
        $service->deleteGroup($taxGroup);
        return back()->with('success', 'Tax group deleted successfully.');
    }
}
