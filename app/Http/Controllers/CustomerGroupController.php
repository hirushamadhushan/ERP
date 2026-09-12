<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerGroupController extends Controller
{
    public function index()
    {
        return view('customer-groups.index', ['groups' => CustomerGroup::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        CustomerGroup::create($this->validated($request));

        return redirect()->route('contacts.groups.index')->with('success', 'Customer group added successfully.');
    }

    public function update(Request $request, CustomerGroup $group)
    {
        $data = $this->validated($request, $group);
        DB::transaction(function () use ($group, $data) {
            $group = CustomerGroup::lockForUpdate()->findOrFail($group->id);
            $previousName = $group->name;
            $group->update($data);
            Contact::whereIn('type', ['customer', 'both'])->where('customer_group', $previousName)
                ->update(['customer_group' => $group->name]);
        });

        return redirect()->route('contacts.groups.index')->with('success', 'Customer group updated successfully.');
    }

    public function destroy(CustomerGroup $group)
    {
        return DB::transaction(function () use ($group) {
            $group = CustomerGroup::lockForUpdate()->findOrFail($group->id);
            if (Contact::whereIn('type', ['customer', 'both'])->where('customer_group', $group->name)->exists()) {
                return back()->with('group_error', 'This group is assigned to customers. Change their group before deleting it.');
            }
            $group->delete();

            return redirect()->route('contacts.groups.index')->with('success', 'Customer group deleted successfully.');
        });
    }

    private function validated(Request $request, ?CustomerGroup $group = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('customer_groups')->ignore($group?->id)],
            'calculation_type' => ['required', Rule::in(['percentage', 'selling_price_group'])],
            'calculation_percentage' => ['exclude_unless:calculation_type,percentage', 'required', 'numeric', 'between:-100,100', 'decimal:0,2'],
            'selling_price_group' => ['exclude_unless:calculation_type,selling_price_group', 'required', 'string', 'max:255'],
        ]);
        $data['calculation_percentage'] = $data['calculation_percentage'] ?? 0;
        $data['selling_price_group'] = $data['selling_price_group'] ?? null;

        return $data;
    }
}
