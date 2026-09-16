<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveWarrantyRequest;
use App\Models\Warranty;

class WarrantyController extends Controller
{
    public function index()
    {
        $records = Warranty::orderBy('name')->get();

        return view('warranties.index', compact('records'));
    }

    public function store(SaveWarrantyRequest $request)
    {
        $validated = $request->validated();

        $this->databaseTransaction(
            fn () => Warranty::create($validated),
            'A warranty with this name already exists.',
            'name'
        );

        return redirect()->route('products.warranties.index')->with('success', 'Warranty added successfully.');
    }

    public function update(SaveWarrantyRequest $request, Warranty $warranty)
    {
        $validated = $request->validated();

        $this->databaseTransaction(
            fn () => $warranty->update($validated),
            'A warranty with this name already exists.',
            'name'
        );

        return redirect()->route('products.warranties.index')->with('success', 'Warranty updated successfully.');
    }

    public function destroy(Warranty $warranty)
    {
        $this->databaseTransaction(
            fn () => $warranty->delete(),
            'This warranty is used by one or more products and cannot be deleted.'
        );

        return redirect()->route('products.warranties.index')->with('success', 'Warranty deleted successfully.');
    }

}
