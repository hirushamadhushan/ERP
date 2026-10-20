<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBrandRequest;
use App\Models\Brand;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class BrandController extends Controller
{
    public function index()
    {
        try {
            return view('products.reference', [
                'kind' => 'brands',
                'singular' => 'Brand',
                'records' => Brand::orderBy('name')->get(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to load brands index: '.$e->getMessage());

            return back()->with('error', 'Unable to load brands.');
        }
    }

    /**
     * Active brands for mobile clients and integrations.
     */
    public function api()
    {
        try {
            return response()->json(
                Brand::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'description', 'use_for_repair'])
            );
        } catch (Throwable $e) {
            Log::error('Failed to load brands API: '.$e->getMessage());

            return response()->json(['error' => 'Unable to load brands.'], 500);
        }
    }

    public function store(SaveBrandRequest $request)
    {
        try {
            $validated = $request->validated();

            $this->databaseTransaction(
                fn () => Brand::create($validated),
                'A brand with this name already exists.',
                'name'
            );

            return redirect()->route('products.brands.index')->with('success', 'Brand added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store brand: '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to add brand. Please try again.');
        }
    }

    public function update(SaveBrandRequest $request, Brand $record)
    {
        try {
            $validated = $request->validated();

            $this->databaseTransaction(
                fn () => $record->update($validated),
                'A brand with this name already exists.',
                'name'
            );

            return redirect()->route('products.brands.index')->with('success', 'Brand updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update brand ID '.$record->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to update brand. Please try again.');
        }
    }

    public function destroy(Brand $record)
    {
        try {
            $this->databaseTransaction(
                fn () => $record->delete(),
                'This brand is used by one or more products and cannot be deleted.'
            );

            return redirect()->route('products.brands.index')->with('success', 'Brand deleted successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete brand ID '.$record->id.': '.$e->getMessage());

            return back()->with('error', 'This brand is used by one or more products and cannot be deleted.');
        }
    }
}
