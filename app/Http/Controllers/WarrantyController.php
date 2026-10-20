<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveWarrantyRequest;
use App\Models\Warranty;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WarrantyController extends Controller
{
    /**
     * Display a listing of all warranties.
     */
    public function index()
    {
        try {
            $records = Warranty::orderBy('name')->get();

            return view('warranties.index', compact('records'));
        } catch (Throwable $e) {
            Log::error('Failed to load warranties index: '.$e->getMessage());

            return back()->with('error', 'Unable to retrieve warranties.');
        }
    }

    /**
     * Provide JSON representation of warranties for client-side dropdowns and API consumers.
     */
    public function api()
    {
        try {
            return response()->json(
                Warranty::orderBy('name')->get(['id', 'name', 'description', 'duration', 'duration_type'])
            );
        } catch (Throwable $e) {
            Log::error('Failed to fetch warranties API: '.$e->getMessage());

            return response()->json(['error' => 'Unable to load warranties.'], 500);
        }
    }

    /**
     * Store a newly created warranty in storage.
     */
    public function store(SaveWarrantyRequest $request)
    {
        try {
            $validated = $request->validated();

            // Run write operation inside atomic transaction with unique constraint translation
            $this->databaseTransaction(
                fn () => Warranty::create($validated),
                'A warranty with this name already exists.',
                'name'
            );

            return redirect()->route('products.warranties.index')->with('success', 'Warranty added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database error creating warranty: '.$e->getMessage(), ['input' => $request->except(['_token'])]);

            return back()->withInput()->with('error', 'A database conflict occurred while saving the warranty.');
        } catch (Throwable $e) {
            Log::error('Unexpected error creating warranty: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'An unexpected error occurred. Please try again.');
        }
    }

    /**
     * Update the specified warranty in storage.
     */
    public function update(SaveWarrantyRequest $request, Warranty $warranty)
    {
        try {
            $validated = $request->validated();

            // Execute atomic update while protecting unique constraints
            $this->databaseTransaction(
                fn () => $warranty->update($validated),
                'A warranty with this name already exists.',
                'name'
            );

            return redirect()->route('products.warranties.index')->with('success', 'Warranty updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database error updating warranty ID '.$warranty->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'A database conflict occurred while updating the warranty.');
        } catch (Throwable $e) {
            Log::error('Unexpected error updating warranty ID '.$warranty->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'An unexpected error occurred. Please try again.');
        }
    }

    /**
     * Remove the specified warranty from storage if no products depend on it.
     */
    public function destroy(Warranty $warranty)
    {
        try {
            // Guard against deleting warranties that are currently assigned to active products
            if ($warranty->products()->exists()) {
                return back()->with('error', 'This warranty is assigned to one or more products and cannot be deleted.');
            }

            $this->databaseTransaction(
                fn () => $warranty->delete(),
                'This warranty is used by one or more products and cannot be deleted.'
            );

            return redirect()->route('products.warranties.index')->with('success', 'Warranty deleted successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database error deleting warranty ID '.$warranty->id.': '.$e->getMessage());

            return back()->with('error', 'This warranty is linked to existing records and cannot be deleted.');
        } catch (Throwable $e) {
            Log::error('Unexpected error deleting warranty ID '.$warranty->id.': '.$e->getMessage());

            return back()->with('error', 'An unexpected error occurred while deleting the warranty.');
        }
    }
}
