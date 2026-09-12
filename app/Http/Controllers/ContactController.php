<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request, string $type)
    {
        abort_unless(array_key_exists($type, Contact::TYPES), 404);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'customer_group' => ['nullable', 'string', 'max:255'],
            'no_sales' => ['nullable', Rule::in(['never', '30', '90', '180', '365'])],
            'due' => ['nullable', 'boolean'], 'returns' => ['nullable', 'boolean'],
            'advance' => ['nullable', 'boolean'], 'opening' => ['nullable', 'boolean'],
        ]);
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

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['contact_id'] = $data['contact_id'] ?? 'C-'.Str::upper((string) Str::ulid());
        $contact = Contact::create($data);

        return redirect()->route('contacts.index', $contact->type === 'both' ? 'customer' : $contact->type)->with('success', 'Contact added successfully.');
    }

    public function show(Contact $contact)
    {
        return response()->json($contact);
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $this->validated($request, $contact);
        $data['contact_id'] = $data['contact_id'] ?? $contact->contact_id;
        $contact->update($data);

        return redirect()->route('contacts.index', $contact->type === 'both' ? 'customer' : $contact->type)->with('success', 'Contact updated successfully.');
    }

    public function destroy(Contact $contact)
    {
        $type = $contact->type === 'both' ? 'customer' : $contact->type;
        $contact->delete();

        return redirect()->route('contacts.index', $type)->with('success', 'Contact deleted successfully.');
    }

    private function validated(Request $request, ?Contact $contact = null): array
    {
        $rules = [
            'type' => ['required', Rule::in(array_keys(Contact::FORM_TYPES))],
            'contact_id' => ['nullable', 'string', 'max:60', Rule::unique('contacts')->ignore($contact?->id)],
            'entity_type' => ['required', Rule::in(['individual', 'business'])],
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'required_if:entity_type,business', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'pay_term' => ['nullable', 'required_with:pay_term_unit', 'integer', 'min:0', 'max:100000'],
            'pay_term_unit' => ['nullable', 'required_with:pay_term', Rule::in(['days', 'months'])],
            'opening_due_cans' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'commission_percentage' => ['nullable', 'required_if:type,commission', 'numeric', 'between:0,100'],
            'custom_fields' => ['nullable', 'array', 'max:10'],
            'custom_fields.*' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
        foreach (['customer_group' => 255, 'alternate_number' => 50, 'landline' => 50, 'tax_number' => 100, 'address_line_1' => 255, 'address_line_2' => 255, 'city' => 100, 'state' => 100, 'country' => 100, 'zip_code' => 30] as $field => $length) {
            $rules[$field] = ['nullable', 'string', 'max:'.$length];
        }
        $rules['customer_group'][] = Rule::exists('customer_groups', 'name');
        $data = $request->validate($rules);
        foreach (['opening_balance', 'opening_due_cans', 'commission_percentage'] as $field) {
            $data[$field] = $data[$field] ?? 0;
        }

        return $data;
    }
}
