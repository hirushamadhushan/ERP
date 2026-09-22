<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveVariationRequest;
use App\Models\VariationTemplate;

class VariationController extends Controller
{
    public function index()
    {
        $records = VariationTemplate::orderBy('name')->get();

        return view('variations.index', compact('records'));
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
        $this->databaseTransaction(
            fn () => $variation->delete(),
            'This variation is used by one or more products and cannot be deleted.'
        );

        return redirect()->route('products.variations.index')->with('success', 'Variation deleted successfully.');
    }

}
