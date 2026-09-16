<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCustomerGroupRequest;
use App\Models\Contact;
use App\Models\CustomerGroup;

class CustomerGroupController extends Controller
{
    public function index()
    {
        return view('customer-groups.index', ['groups' => CustomerGroup::orderBy('name')->get()]);
    }

    public function store(SaveCustomerGroupRequest $request)
    {
        $validated = $request->groupData();

        $this->databaseTransaction(
            fn () => CustomerGroup::create($validated),
            'A customer group with this name already exists.',
            'name'
        );

        return redirect()->route('contacts.groups.index')->with('success', 'Customer group added successfully.');
    }

    public function update(SaveCustomerGroupRequest $request, CustomerGroup $group)
    {
        $data = $request->groupData();
        $this->databaseTransaction(function () use ($group, $data) {
            // Lock the group while its denormalized name is propagated to contacts.
            $group = CustomerGroup::lockForUpdate()->findOrFail($group->id);
            $previousName = $group->name;
            $group->update($data);
            Contact::whereIn('type', ['customer', 'both'])->where('customer_group', $previousName)
                ->update(['customer_group' => $group->name]);
        }, 'A customer group with this name already exists.', 'name');

        return redirect()->route('contacts.groups.index')->with('success', 'Customer group updated successfully.');
    }

    public function destroy(CustomerGroup $group)
    {
        return $this->databaseTransaction(function () use ($group) {
            $group = CustomerGroup::lockForUpdate()->findOrFail($group->id);
            if (Contact::whereIn('type', ['customer', 'both'])->where('customer_group', $group->name)->exists()) {
                return back()->with('group_error', 'This group is assigned to customers. Change their group before deleting it.');
            }
            $group->delete();

            return redirect()->route('contacts.groups.index')->with('success', 'Customer group deleted successfully.');
        }, 'This customer group is still linked to existing records.');
    }

}
