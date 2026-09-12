@extends('layouts.app')

@section('title', $title)
@section('subtitle', $type === 'commission' ? 'Manage commission agents' : 'Manage your '.strtolower($title))

@section('content')
@php
    $inputClass = 'w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-500';
    $labelClass = 'block text-xs font-bold text-slate-700 mb-1.5';
    $primaryClass = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-md shadow-purple-500/20 transition-colors';
    $secondaryClass = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition-colors';
    $columns = ['contact_id' => 'Contact ID', 'business_name' => 'Business Name', 'name' => 'Name', 'email' => 'Email'];
    if ($type === 'commission') {
        $columns += ['commission_percentage' => 'Commission %'];
    } else {
        $columns += ['tax_number' => 'Tax number'];
        if ($type === 'customer') $columns += ['credit_limit' => 'Credit Limit'];
        $columns += ['pay_term' => 'Pay term', 'opening_balance' => 'Opening Balance', 'advance_balance' => 'Advance Balance'];
    }
    $columns += ['created_at' => 'Added On'];
    if ($type === 'customer') {
        $columns += [
            'customer_group' => 'Customer Group', 'address' => 'Address', 'mobile' => 'Mobile',
            'due_balance' => 'Total Sale Due', 'return_balance' => 'Total Sell Return Due',
        ];
    } elseif ($type === 'supplier') {
        $columns += [
            'address' => 'Address', 'mobile' => 'Mobile',
            'due_balance' => 'Total Purchase Due', 'return_balance' => 'Total Purchase Return Due',
        ];
    } else {
        $columns += ['address' => 'Address', 'mobile' => 'Mobile', 'status' => 'Status', 'assigned_user' => 'Assigned to'];
    }
    if (in_array($type, ['customer', 'supplier'])) {
        for ($i = 0; $i < 10; $i++) {
            $columns['custom_fields.'.$i] = 'Custom Field '.($i + 1);
        }
    }
    $moneyColumns = ['opening_balance', 'advance_balance', 'due_balance', 'return_balance'];
@endphp

<div class="space-y-6" id="contacts-page">
    @if(session('success'))
        <div role="status" class="flex items-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ session('success') }}</div>
    @endif
    <div id="contact-load-error" role="alert" hidden class="px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm"></div>

    <details open class="bg-white rounded-2xl border border-purple-100 shadow-sm overflow-hidden">
        <summary class="px-6 py-4 bg-purple-50/50 border-b border-purple-100 text-sm font-bold text-purple-700 cursor-pointer"><i class="bi bi-funnel-fill mr-2" aria-hidden="true"></i>Filters</summary>
        <form method="GET" action="{{ route('contacts.index', $type) }}" class="p-6 space-y-5">
            @if($type !== 'commission')
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach(['due' => $type === 'customer' ? 'Sell Due' : 'Purchase Due', 'returns' => $type === 'customer' ? 'Sell Return' : 'Purchase Return', 'advance' => 'Advance Balance', 'opening' => 'Opening Balance'] as $key => $label)
                        <label class="inline-flex items-center gap-3 text-xs font-semibold text-slate-700 cursor-pointer"><input type="checkbox" name="{{ $key }}" value="1" @checked(request()->boolean($key)) class="w-4 h-4 accent-purple-600 rounded">{{ $label }}</label>
                    @endforeach
                </div>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @if($type === 'customer')
                    <div><label for="filter-no-sales" class="{{ $labelClass }}">Has no sell from</label><select id="filter-no-sales" name="no_sales" class="{{ $inputClass }}"><option value="">All customers</option>@foreach(['never' => 'No recorded sales', '30' => 'Last 30 days', '90' => 'Last 90 days', '180' => 'Last 180 days', '365' => 'Last year'] as $value => $label)<option value="{{ $value }}" @selected(request('no_sales') == $value)>{{ $label }}</option>@endforeach</select></div>
                    <div><label for="filter-group" class="{{ $labelClass }}">Customer Group</label><select id="filter-group" name="customer_group" class="{{ $inputClass }}"><option value="">All groups</option>@foreach($groups as $group)<option @selected(request('customer_group') === $group)>{{ $group }}</option>@endforeach</select></div>
                @endif
                <div><label for="filter-assigned" class="{{ $labelClass }}">Assigned to</label><select id="filter-assigned" name="assigned_to" class="{{ $inputClass }}"><option value="">All users</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected(request('assigned_to') == $assignee->id)>{{ $assignee->name }}</option>@endforeach</select></div>
                <div><label for="filter-status" class="{{ $labelClass }}">Status</label><select id="filter-status" name="status" class="{{ $inputClass }}"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></div>
            </div>
            <div class="flex items-center gap-2"><button type="submit" class="{{ $primaryClass }}"><i class="bi bi-funnel" aria-hidden="true"></i>Apply filters</button><a href="{{ route('contacts.index', $type) }}" class="{{ $secondaryClass }}">Reset</a></div>
        </form>
    </details>

    <section class="bg-white rounded-2xl border border-purple-100 shadow-sm p-4 sm:p-6">
        <div class="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
            <div><h2 class="text-lg font-bold text-slate-900">{{ $type === 'commission' ? 'All commission agents' : 'All your '.$title }}</h2><p class="text-xs text-slate-400 mt-1">{{ $type === 'commission' ? 'Manage agent details and commission rates' : 'View, add and manage your '.strtolower($title) }}</p></div>
            <button id="add-contact" type="button" class="{{ $primaryClass }} shrink-0"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add</button>
        </div>
        <div class="overflow-x-auto">
            <table id="contacts-table" class="w-full text-left" style="width:100%">
                <thead><tr><th>Action</th>@foreach($columns as $key => $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($contacts as $contact)
                    <tr>
                        <td class="whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <button type="button" data-contact-url="{{ route('contacts.show', $contact) }}" data-mode="view" class="contact-action px-2.5 py-1.5 rounded-lg text-xs font-bold text-sky-700 bg-sky-50 hover:bg-sky-100"><i class="bi bi-eye" aria-hidden="true"></i> View</button>
                                <button type="button" data-contact-url="{{ route('contacts.show', $contact) }}" data-mode="edit" class="contact-action px-2.5 py-1.5 rounded-lg text-xs font-bold text-purple-700 bg-purple-50 hover:bg-purple-100"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</button>
                                <form method="POST" action="{{ route('contacts.destroy', $contact) }}" class="delete-contact inline">@csrf @method('DELETE')<button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100" aria-label="Delete {{ $contact->name }}"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
                            </div>
                        </td>
                        @foreach($columns as $key => $label)
                            <td class="{{ in_array($key, $moneyColumns) ? 'whitespace-nowrap' : '' }}">
                                @if(in_array($key, $moneyColumns))
                                    Rs. {{ number_format((float) $contact->$key, 2) }}
                                @elseif(str_starts_with($key, 'custom_fields.'))
                                    {{ data_get($contact->custom_fields, substr($key, strlen('custom_fields.'))) ?? '' }}
                                @elseif($key === 'credit_limit')
                                    {{ $contact->credit_limit === null ? 'No Limit' : 'Rs. '.number_format((float) $contact->credit_limit, 2) }}
                                @elseif($key === 'commission_percentage')
                                    {{ $contact->commission_percentage }}%
                                @elseif($key === 'created_at')
                                    {{ $contact->created_at->format('Y-m-d') }}
                                @elseif($key === 'address')
                                    {{ collect([$contact->address_line_1, $contact->address_line_2, $contact->city, $contact->state, $contact->country, $contact->zip_code])->filter()->implode(', ') ?: '—' }}
                                @elseif($key === 'assigned_user')
                                    {{ $contact->assignedUser?->name ?? 'Unassigned' }}
                                @elseif($key === 'pay_term')
                                    {{ $contact->pay_term !== null ? $contact->pay_term.' '.$contact->pay_term_unit : '—' }}
                                @elseif($key === 'status')
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $contact->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ ucfirst($contact->status) }}</span>
                                @else
                                    {{ $contact->$key ?: '—' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="bg-purple-50 text-slate-700 text-xs font-bold"><tr><th class="p-3">Total (filtered)</th>@foreach($columns as $key => $label)<th class="p-3 whitespace-nowrap" @if(in_array($key, $moneyColumns)) data-total="true" @endif>{{ in_array($key, $moneyColumns) ? 'Rs. '.number_format((float) $contacts->sum($key), 2) : '' }}</th>@endforeach</tr></tfoot>
            </table>
        </div>
    </section>
</div>

@include('contacts.form')
@endsection

@push('scripts')
<style>
    #contact-dialog { border: 0; margin: auto; padding: 0; width: calc(100% - 2rem); max-width: 64rem; max-height: calc(100dvh - 2rem); border-radius: 1rem; }
    #contact-dialog::backdrop { background: rgb(15 23 42 / .5); backdrop-filter: blur(3px); }
    #contacts-page .dataTables_wrapper .dt-buttons { display: flex; flex-wrap: wrap; gap: .25rem; }
    #contacts-page .dataTables_wrapper .dataTables_filter, #contacts-page .dataTables_wrapper .dataTables_length { float: none; text-align: left; }
    #contacts-page table.dataTable { min-width: 1050px; }
</style>
@include('contacts.scripts')
@endpush
