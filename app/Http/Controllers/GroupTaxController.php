<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaxGroupRequest;
use App\Models\TaxRate;
use App\Services\TaxRateService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class GroupTaxController extends Controller
{
    public function store(SaveTaxGroupRequest $request, TaxRateService $service)
    {
        try {
            $service->saveGroup($request->validated());

            return back()->with('success', 'Tax group added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to create tax group: '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to add tax group.');
        }
    }

    public function update(SaveTaxGroupRequest $request, TaxRate $taxGroup, TaxRateService $service)
    {
        try {
            $service->saveGroup($request->validated(), $taxGroup);

            return back()->with('success', 'Tax group updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update tax group ID '.$taxGroup->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to update tax group.');
        }
    }

    public function destroy(TaxRate $taxGroup, TaxRateService $service)
    {
        try {
            $service->deleteGroup($taxGroup);

            return back()->with('success', 'Tax group deleted successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete tax group ID '.$taxGroup->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to delete tax group.');
        }
    }
}
