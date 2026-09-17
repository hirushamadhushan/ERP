<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBusinessLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BusinessLocationController extends Controller
{
    public function index(): View
    {
        return view('business.locations', [
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function store(SaveBusinessLocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return redirect()->route('business.locations.index')->with('status', 'Business location added successfully.');
    }

    public function update(SaveBusinessLocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()->route('business.locations.index')->with('status', 'Business location updated successfully.');
    }

    public function toggle(Location $location): RedirectResponse
    {
        $location->update(['is_active' => ! $location->is_active]);

        return redirect()->route('business.locations.index')->with('status', $location->is_active ? 'Business location activated.' : 'Business location deactivated.');
    }
}
