<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBrandRequest;
use App\Models\Brand;

class BrandController extends Controller
{
    public function index()
    {
        return view('products.reference', ['kind' => 'brands', 'singular' => 'Brand', 'records' => Brand::orderBy('name')->get()]);
    }

    public function store(SaveBrandRequest $request)
    {
        $validated = $request->validated();

        $this->databaseTransaction(
            fn () => Brand::create($validated),
            'A brand with this name already exists.',
            'name'
        );

        return redirect()->route('products.brands.index')->with('success', 'Brand added successfully.');
    }

    public function update(SaveBrandRequest $request, Brand $record)
    {
        $validated = $request->validated();

        $this->databaseTransaction(
            fn () => $record->update($validated),
            'A brand with this name already exists.',
            'name'
        );

        return redirect()->route('products.brands.index')->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $record)
    {
        $this->databaseTransaction(
            fn () => $record->delete(),
            'This brand is used by one or more products and cannot be deleted.'
        );

        return redirect()->route('products.brands.index')->with('success', 'Brand deleted successfully.');
    }

}
