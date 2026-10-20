<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaxRateRequest;
use App\Models\TaxRate;
use App\Services\TaxRateService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaxRateController extends Controller
{
    public function index()
    {
        try {
            return view('tax-rates.index', [
                'taxRates' => TaxRate::singles()->withCount('taxGroups')->orderBy('name')->get(),
                'taxGroups' => TaxRate::groups()->with('subTaxes')->orderBy('name')->get(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to load tax rates index: '.$e->getMessage());

            return back()->with('error', 'Unable to load tax rates.');
        }
    }

    public function store(SaveTaxRateRequest $request, TaxRateService $service)
    {
        try {
            $service->saveSingle($request->validated());

            return back()->with('success', 'Tax rate added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store tax rate: '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to add tax rate.');
        }
    }

    public function update(SaveTaxRateRequest $request, TaxRate $taxRate, TaxRateService $service)
    {
        try {
            $service->saveSingle($request->validated(), $taxRate);

            return back()->with('success', 'Tax rate updated successfully. Related group totals were recalculated.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update tax rate ID '.$taxRate->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to update tax rate.');
        }
    }

    public function destroy(TaxRate $taxRate, TaxRateService $service)
    {
        try {
            $service->deleteSingle($taxRate);

            return back()->with('success', 'Tax rate deleted successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete tax rate ID '.$taxRate->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to delete tax rate.');
        }
    }
}
