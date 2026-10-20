<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCustomerGroupRequest;
use App\Models\Contact;
use App\Models\CustomerGroup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerGroupController extends Controller
{
    public function index()
    {
        try {
            return view('customer-groups.index', ['groups' => CustomerGroup::orderBy('name')->get()]);
        } catch (Throwable $e) {
            Log::error('Failed to load customer groups index: '.$e->getMessage());

            return back()->with('error', 'Unable to load customer groups.');
        }
    }

    public function store(SaveCustomerGroupRequest $request)
    {
        try {
            $validated = $request->groupData();

            $this->databaseTransaction(
                fn () => CustomerGroup::create($validated),
                'A customer group with this name already exists.',
                'name'
            );

            return redirect()->route('contacts.groups.index')->with('success', 'Customer group added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store customer group: '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to add customer group. Please try again.');
        }
    }

    public function update(SaveCustomerGroupRequest $request, CustomerGroup $group)
    {
        try {
            $data = $request->groupData();
            $this->databaseTransaction(function () use ($group, $data) {
                // Lock the group while its denormalized name is propagated to contacts.
                $group = CustomerGroup::lockForUpdate()->findOrFail($group->id);
                $previousName = $group->name;
                $group->update($data);
                if (! Schema::hasColumn('contacts', 'customer_group_id')) {
                    Contact::whereIn('type', ['customer', 'both'])->where('customer_group', $previousName)->update(['customer_group' => $group->name]);
                }
            }, 'A customer group with this name already exists.', 'name');

            return redirect()->route('contacts.groups.index')->with('success', 'Customer group updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update customer group ID '.$group->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'Unable to update customer group. Please try again.');
        }
    }

    public function destroy(CustomerGroup $group)
    {
        try {
            return $this->databaseTransaction(function () use ($group) {
                $group = CustomerGroup::lockForUpdate()->findOrFail($group->id);
                $assigned = Schema::hasColumn('contacts', 'customer_group_id')
                    ? Contact::whereIn('type', ['customer', 'both'])->where('customer_group_id', $group->id)->exists()
                    : Contact::whereIn('type', ['customer', 'both'])->where('customer_group', $group->name)->exists();
                if ($assigned) {
                    return back()->with('group_error', 'This group is assigned to customers. Change their group before deleting it.');
                }
                $group->delete();

                return redirect()->route('contacts.groups.index')->with('success', 'Customer group deleted successfully.');
            }, 'This customer group is still linked to existing records.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete customer group ID '.$group->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to delete customer group.');
        }
    }
}
