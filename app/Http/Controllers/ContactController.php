<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactFilterRequest;
use App\Http\Requests\SaveContactRequest;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Models\SalesInvoice;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Models\CustomerDocument;
use App\Models\CustomerNote;
use App\Services\CustomerManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContactController extends Controller
{
    public function index(ContactFilterRequest $request, string $type)
    {
        abort_unless(array_key_exists($type, Contact::TYPES), 404);
        $filters = $request->validated();
        $query = Contact::with(['assignedUser', 'customerGroupRecord'])->whereIn('type', in_array($type, ['customer', 'supplier']) ? [$type, 'both'] : [$type]);
        if (in_array($type, ['customer', 'supplier'])) $this->applyContactVisibility($query, $type);
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
        try {
            $data = $request->contactData();
            $this->authorizeContactType($data['type']);
            $this->assignOwnedContact($data);
            $this->normalizeCustomerGroup($data);
            $data['contact_id'] = $data['contact_id'] ?? 'C-'.Str::upper((string) Str::ulid());

            $contact = $this->databaseTransaction(
                function () use ($data) {
                    $contact = Contact::create($data);
                    app(\App\Services\CustomerBalanceService::class)->refresh($contact);
                    return $contact;
                },
                'This Contact ID is already in use.',
                'contact_id'
            );

            return redirect()->route('contacts.index', $contact->type === 'both' ? 'customer' : $contact->type)->with('success', 'Contact added successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create contact: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Unable to create contact. Please verify your data.');
        }
    }

    public function show(Contact $contact)
    {
        if (in_array($contact->type, ['customer', 'both'])) $this->ensureContactVisible($contact, 'customer');
        if (in_array($contact->type, ['supplier', 'both'])) $this->ensureContactVisible($contact, 'supplier');
        return response()->json($contact);
    }

    public function update(SaveContactRequest $request, Contact $contact)
    {
        try {
            $this->ensureRecordVisible($contact);
            $data = $request->contactData();
            $this->authorizeContactType($data['type']);
            $this->assignOwnedContact($data);
            $this->normalizeCustomerGroup($data);
            $data['contact_id'] = $data['contact_id'] ?? $contact->contact_id;

            $this->databaseTransaction(
                function () use ($contact, $data) {
                    $contact->update($data);
                    app(\App\Services\CustomerBalanceService::class)->refresh($contact);
                },
                'This Contact ID is already in use.',
                'contact_id'
            );

            return redirect()->route('contacts.index', $contact->type === 'both' ? 'customer' : $contact->type)->with('success', 'Contact updated successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update contact ID '.$contact->id.': '.$e->getMessage());
            return back()->withInput()->with('error', 'Unable to update contact. Please verify your data.');
        }
    }

    private function normalizeCustomerGroup(array &$data): void
    {
        if (! Schema::hasColumn('contacts', 'customer_group_id')) return;
        $data['customer_group_id'] = empty($data['customer_group']) ? null : CustomerGroup::where('name', $data['customer_group'])->value('id');
        unset($data['customer_group']);
    }

    public function destroy(Contact $contact)
    {
        try {
            $this->ensureRecordVisible($contact);
            $type = $contact->type === 'both' ? 'customer' : $contact->type;
            $this->databaseTransaction(
                fn () => $contact->delete(),
                'This contact is linked to another record and cannot be deleted.'
            );

            return redirect()->route('contacts.index', $type)->with('success', 'Contact deleted successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to delete contact ID '.$contact->id.': '.$e->getMessage());
            return back()->with('error', 'Unable to delete contact because it is linked to other records.');
        }
    }

    public function ledger(Contact $contact)
    {
        $this->customerOnly($contact);
        return view('contacts.ledger', ['contact'=>$contact, 'payments'=>$contact->customerPayments()->with('creator')->latest('paid_at')->get(), 'invoices'=>SalesInvoice::with(['payments','creditNotes'])->where('customer_id',$contact->id)->where('status','!=','draft')->orderBy('invoice_date')->get()]);
    }

    public function salesHistory(Contact $contact)
    {
        $this->customerOnly($contact);
        return view('contacts.sales-history', ['contact'=>$contact, 'sales'=>SalesInvoice::where('customer_id',$contact->id)->where('status','!=','draft')->latest('invoice_date')->get()]);
    }

    public function collectDue(Request $request, Contact $contact, CustomerManagementService $service)
    {
        $this->customerOnly($contact);
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', 'max:40'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $payment = $service->collectDue($contact, $data, (int) auth()->id());
            return response()->json([
                'message' => 'Payment received. Receipt '.$payment->receipt_no.' created.',
                'receipt_no' => $payment->receipt_no,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to collect due for customer ID '.$contact->id.': '.$e->getMessage());
            return response()->json(['message' => 'Payment collection failed: '.$e->getMessage()], 422);
        }
    }

    public function documents(Contact $contact)
    {
        $this->customerOnly($contact);
        return response()->json([
            'documents' => $contact->customerDocuments()->with('creator')->latest()->get(),
            'notes' => $contact->customerNotes()->with('creator')->latest()->get(),
        ]);
    }

    public function storeDocument(Request $request, Contact $contact)
    {
        $this->customerOnly($contact);
        $data = $request->validate([
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $filePath = null;
        try {
            $file = $data['document'];
            $filePath = $file->store('customer-documents', 'local');

            $record = $contact->customerDocuments()->create([
                'name' => $file->getClientOriginalName(),
                'path' => $filePath,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'created_by' => auth()->id(),
            ]);

            if (filled($data['note'] ?? null)) {
                $contact->customerNotes()->create([
                    'body' => $data['note'],
                    'created_by' => auth()->id(),
                ]);
            }

            return response()->json(['message' => 'Customer document saved.', 'document' => $record]);
        } catch (\Throwable $e) {
            if ($filePath) {
                Storage::disk('local')->delete($filePath);
            }
            \Illuminate\Support\Facades\Log::error('Failed to upload customer document: '.$e->getMessage());
            return response()->json(['message' => 'Unable to save document. Please try again.'], 500);
        }
    }

    public function storeNote(Request $request, Contact $contact)
    {
        $this->customerOnly($contact);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        try {
            $note = $contact->customerNotes()->create($data + ['created_by' => auth()->id()]);
            return response()->json(['message' => 'Note saved.', 'note' => $note]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to store customer note: '.$e->getMessage());
            return response()->json(['message' => 'Unable to save note.'], 500);
        }
    }

    public function downloadDocument(CustomerDocument $document)
    {
        $this->customerOnly($document->customer);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        return Storage::disk('local')->download($document->path, $document->name);
    }

    public function toggleStatus(Contact $contact)
    {
        $this->customerOnly($contact);
        try {
            $contact->update(['status' => $contact->status === 'active' ? 'inactive' : 'active']);
            return response()->json(['message' => 'Customer status updated.', 'status' => $contact->status]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to toggle contact status: '.$e->getMessage());
            return response()->json(['message' => 'Unable to update status.'], 500);
        }
    }

    public function toggleSupplierStatus(Contact $contact)
    {
        $this->supplierOnly($contact);
        try {
            $contact->update(['status' => $contact->status === 'active' ? 'inactive' : 'active']);
            return response()->json(['message' => 'Supplier status updated.', 'status' => $contact->status]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to toggle supplier status: '.$e->getMessage());
            return response()->json(['message' => 'Unable to update status.'], 500);
        }
    }
    private function customerOnly(Contact $contact): void { abort_unless(in_array($contact->type,['customer','both']),404); $this->ensureContactVisible($contact, 'customer'); }
    private function supplierOnly(Contact $contact): void { abort_unless(in_array($contact->type,['supplier','both']),404); $this->ensureContactVisible($contact, 'supplier'); }
    private function ensureRecordVisible(Contact $contact): void
    {
        foreach (['customer', 'supplier'] as $type) {
            if (in_array($contact->type, [$type, 'both'], true)) $this->ensureContactVisible($contact, $type);
        }
    }
    private function authorizeContactType(string $type): void
    {
        $role = auth()->user()?->assignedRole;
        if (! $role || strcasecmp($role->name, 'admin') === 0) return;

        foreach (['customer', 'supplier'] as $contactType) {
            if (! in_array($type, [$contactType, 'both'], true)) continue;
            abort_unless(
                in_array($contactType.'.view', $role->permissions, true)
                || in_array($contactType.'.view_own', $role->permissions, true),
                403
            );
        }
    }
    private function assignOwnedContact(array &$data): void
    {
        $role = auth()->user()?->assignedRole;
        if (! $role || strcasecmp($role->name, 'admin') === 0) return;

        foreach (['customer', 'supplier'] as $type) {
            if (in_array($data['type'], [$type, 'both'], true)
                && ! in_array($type.'.view', $role->permissions, true)) {
                $data['assigned_to'] = auth()->id();
            }
        }
    }
    private function applyContactVisibility($query, string $type): void
    {
        $user=auth()->user(); $role=$user?->assignedRole;
        // Legacy accounts without a role retain access while configured roles use the explicit customer permissions.
        if (! $role || strtolower($role->name) === 'admin' || in_array($type.'.view', $role->permissions, true)) return;
        abort_unless(in_array($type.'.view_own', $role->permissions, true), 403);
        $query->where(fn ($contacts) => $contacts->where('assigned_to', $user->id)->orWhereIn('id', $user->selectedContacts()->select('contacts.id')));
    }
    private function ensureContactVisible(Contact $contact, string $type): void
    {
        $query=Contact::query()->whereKey($contact->id); $this->applyContactVisibility($query, $type); abort_unless($query->exists(),403);
    }

}
