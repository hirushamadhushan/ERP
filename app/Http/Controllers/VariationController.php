<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveVariationRequest;
use App\Models\VariationTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class VariationController extends Controller
{
    public function index()
    {
        try {
            $records = VariationTemplate::with('valueRecords')->orderBy('name')->get();

            return view('variations.index', compact('records'));
        } catch (Throwable $e) {
            Log::error('Failed to load variations index: '.$e->getMessage());

            return back()->with('error', 'Unable to load variations.');
        }
    }

    /**
     * Active variation templates and their ordered values for product forms,
     * mobile clients and integrations.
     */
    public function api()
    {
        try {
            return response()->json(
                VariationTemplate::with('valueRecords:id,variation_template_id,value,sort_order')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (VariationTemplate $template) => [
                        'id' => $template->id,
                        'name' => $template->name,
                        'values' => $template->valueRecords->pluck('value')->values(),
                    ])
            );
        } catch (Throwable $e) {
            Log::error('Failed to load variations API: '.$e->getMessage());

            return response()->json(['error' => 'Unable to load variations.'], 500);
        }
    }

    public function store(SaveVariationRequest $request)
    {
        try {
            $validated = $request->variationData();

            $this->databaseTransaction(
                function () use ($validated) {
                    $values = $validated['values'];
                    unset($validated['values']);
                    $variation = VariationTemplate::create($validated);
                    $variation->syncValues($values);
                },
                'A variation with this name already exists.',
                'name'
            );

            return redirect()->route('products.variations.index')->with('success', 'Variation added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store variation: '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to add variation. Please try again.');
        }
    }

    public function update(SaveVariationRequest $request, VariationTemplate $variation)
    {
        try {
            $validated = $request->variationData();

            $this->databaseTransaction(
                function () use ($variation, $validated) {
                    $values = $validated['values'];
                    unset($validated['values']);
                    $variation->update($validated);
                    $variation->syncValues($values);
                },
                'A variation with this name already exists.',
                'name'
            );

            return redirect()->route('products.variations.index')->with('success', 'Variation updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update variation ID '.$variation->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to update variation. Please try again.');
        }
    }

    public function destroy(VariationTemplate $variation)
    {
        try {
            if ($variation->valueRecords()->whereHas('productVariants')->exists()) {
                return back()->with('error', 'This variation is used by one or more products and cannot be deleted.');
            }

            $this->databaseTransaction(
                fn () => $variation->delete(),
                'This variation is used by one or more products and cannot be deleted.'
            );

            return redirect()->route('products.variations.index')->with('success', 'Variation deleted successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete variation ID '.$variation->id.': '.$e->getMessage());

            return back()->with('error', 'This variation is used by one or more products and cannot be deleted.');
        }
    }
}
