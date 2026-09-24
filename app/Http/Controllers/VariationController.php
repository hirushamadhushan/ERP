<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveVariationRequest;
use App\Models\VariationTemplate;

class VariationController extends Controller
{
    public function index()
    {
        $records = VariationTemplate::with('valueRecords')->orderBy('name')->get();

        return view('variations.index', compact('records'));
    }

    /**
     * Active variation templates and their ordered values for product forms,
     * mobile clients and integrations.
     */
    public function api()
    {
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
    }

    public function store(SaveVariationRequest $request)
    {
        $validated = $request->variationData();

        $this->databaseTransaction(
            function () use ($validated) { $values = $validated['values']; unset($validated['values']); $variation = VariationTemplate::create($validated); $variation->syncValues($values); },
            'A variation with this name already exists.',
            'name'
        );

        return redirect()->route('products.variations.index')->with('success', 'Variation added successfully.');
    }

    public function update(SaveVariationRequest $request, VariationTemplate $variation)
    {
        $validated = $request->variationData();

        $this->databaseTransaction(
            function () use ($variation, $validated) { $values = $validated['values']; unset($validated['values']); $variation->update($validated); $variation->syncValues($values); },
            'A variation with this name already exists.',
            'name'
        );

        return redirect()->route('products.variations.index')->with('success', 'Variation updated successfully.');
    }

    public function destroy(VariationTemplate $variation)
    {
        if ($variation->valueRecords()->whereHas('productVariants')->exists()) {
            return back()->with('error', 'This variation is used by one or more products and cannot be deleted.');
        }

        $this->databaseTransaction(
            fn () => $variation->delete(),
            'This variation is used by one or more products and cannot be deleted.'
        );

        return redirect()->route('products.variations.index')->with('success', 'Variation deleted successfully.');
    }

}
