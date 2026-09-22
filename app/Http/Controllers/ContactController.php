<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactFilterRequest;
use App\Http\Requests\SaveContactRequest;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ContactController extends Controller
{
    public function index(ContactFilterRequest $request, string $type)
    {
        abort_unless(array_key_exists($type, Contact::TYPES), 404);
        $filters = $request->validated();
        $query = Contact::with(['assignedUser', 'customerGroupRecord'])->whereIn('type', in_array($type, ['customer', 'supplier']) ? [$type, 'both'] : [$type]);
        foreach (['status', 'assigned_to'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['customer_group'])) $query->whereHas('customerGroupRecord', fn ($q) => $q->where('name', $filters['customer_group']));
        foreach (['due' => 'due_balance', 'returns' => 'return_balance', 'advance' => 'advance_balance', 'opening' => 'opening_balance'] as $filter => $column) {
            if ($request->boolean($filter)) {
                $query->where($column, '>', 0);
            }
        }
        if ($type === 'customer' && ! empty($filters['no_sales'])) {
            $query->where(function ($query) use ($filters) {
                $query->whereNull('last_sale_at');
                if ($filters['no_sales'] !== 'never') {
                    $query->orWhere('last_sale_at', '<', now()->subDays((int) $filters['no_sales'])->toDateString());
                }
            });
        }

        return view('contacts.index', [
            'type' => $type, 'title' => Contact::TYPES[$type],
            'contacts' => $query->latest()->get(),
            'assignees' => User::orderBy('name')->get(['id', 'name']),
            'groups' => CustomerGroup::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(SaveContactRequest $request)
    {
        $data = $request->contactData();
        $this->normalizeCustomerGroup($data);
        $data['contact_id'] = $data['contact_id'] ?? 'C-'.Str::upper((string) Str::ulid());
        $contact = $this->databaseTransaction(
            fn () => Contact::create($data),
            'This Contact ID is already in use.',
            'contact_id'
        );

        return redirect()->route('contacts.index', $contact->type === 'both' ? 'customer' : $contact->type)->with('success', 'Contact added successfully.');
    }

    public function show(Contact $contact)
    {
        return response()->json($contact);
    }

    public function update(SaveContactRequest $request, Contact $contact)
    {
        $data = $request->contactData();
        $this->normalizeCustomerGroup($data);
        $data['contact_id'] = $data['contact_id'] ?? $contact->contact_id;
        $this->databaseTransaction(
            fn () => $contact->update($data),
            'This Contact ID is already in use.',
            'contact_id'
        );

        return redirect()->route('contacts.index', $contact->type === 'both' ? 'customer' : $contact->type)->with('success', 'Contact updated successfully.');
    }

    private function normalizeCustomerGroup(array &$data): void
    {
        if (! Schema::hasColumn('contacts', 'customer_group_id')) return;
        $data['customer_group_id'] = empty($data['customer_group']) ? null : CustomerGroup::where('name', $data['customer_group'])->value('id');
        unset($data['customer_group']);
    }

    public function destroy(Contact $contact)
    {
        $type = $contact->type === 'both' ? 'customer' : $contact->type;
        $this->databaseTransaction(
            fn () => $contact->delete(),
            'This contact is linked to another record and cannot be deleted.'
        );

        return redirect()->route('contacts.index', $type)->with('success', 'Contact deleted successfully.');
    }

}
