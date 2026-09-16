<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactFilterRequest;
use App\Http\Requests\SaveContactRequest;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function index(ContactFilterRequest $request, string $type)
    {
        abort_unless(array_key_exists($type, Contact::TYPES), 404);
        $filters = $request->validated();
        $query = Contact::with('assignedUser')->whereIn('type', in_array($type, ['customer', 'supplier']) ? [$type, 'both'] : [$type]);
        foreach (['status', 'assigned_to', 'customer_group'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
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
        $data['contact_id'] = $data['contact_id'] ?? $contact->contact_id;
        $this->databaseTransaction(
            fn () => $contact->update($data),
            'This Contact ID is already in use.',
            'contact_id'
        );

        return redirect()->route('contacts.index', $contact->type === 'both' ? 'customer' : $contact->type)->with('success', 'Contact updated successfully.');
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
