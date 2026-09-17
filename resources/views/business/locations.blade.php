@extends('layouts.app')
@section('title', 'Business Locations')
@section('content')
@php
    $fieldClass = 'w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100';
@endphp
@if(session('status'))<div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
<div class="mb-5 flex flex-wrap items-baseline gap-3">
    <h1 class="text-xl font-bold text-slate-900">Business Locations</h1>
    <span class="text-sm text-slate-500">Manage your business locations</span>
</div>

<section class="rounded-2xl border border-purple-100 bg-white p-4 shadow-sm sm:p-6">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 class="text-base font-bold text-slate-900">All your business locations</h2>
        <button id="add-location" type="button" class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-purple-500/20 hover:bg-purple-700"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add</button>
    </div>
    <div class="sticky-table-host">
        <table id="locations-table" class="w-full text-left" style="min-width:1450px">
            <thead><tr>
                <th>Name</th><th>Location ID</th><th>Landmark</th><th>City</th><th>Zip Code</th><th>State</th><th>Country</th><th>Price Group</th><th>Invoice scheme</th><th>Invoice layout for POS</th><th>Invoice layout for sale</th><th>Action</th>
            </tr></thead>
            <tbody>
            @foreach($locations as $location)
                @php($locationData = $location->only(['id','name','code','landmark','city','zip_code','state','country','price_group','invoice_scheme','invoice_layout_pos','invoice_layout_sale']))
                <tr>
                    <td class="font-semibold text-slate-800">{{ $location->name }}@unless($location->is_active)<span class="mt-1 block text-xs font-medium text-slate-500">Inactive</span>@endunless</td>
                    <td>{{ $location->code }}</td>
                    <td>{{ $location->landmark ?: '—' }}</td>
                    <td>{{ $location->city ?: '—' }}</td>
                    <td>{{ $location->zip_code ?: '—' }}</td>
                    <td>{{ $location->state ?: '—' }}</td>
                    <td>{{ $location->country ?: '—' }}</td>
                    <td>{{ $location->price_group ?: '—' }}</td>
                    <td>{{ $location->invoice_scheme }}</td>
                    <td>{{ $location->invoice_layout_pos }}</td>
                    <td>{{ $location->invoice_layout_sale }}</td>
                    <td><div class="flex min-w-max flex-wrap gap-1.5">
                        <button type="button" class="edit-location rounded-lg bg-purple-50 px-2.5 py-1.5 text-xs font-bold text-purple-700 hover:bg-purple-100" data-location='@json($locationData)' data-url="{{ route('business.locations.update', $location) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Edit</button>
                        <button type="button" class="settings-location rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200" data-location='@json($locationData)' data-url="{{ route('business.locations.update', $location) }}"><i class="bi bi-sliders" aria-hidden="true"></i> Settings</button>
                        <form method="POST" action="{{ route('business.locations.toggle', $location) }}" onsubmit="return confirm('{{ $location->is_active ? 'Deactivate' : 'Activate' }} this business location?')">@csrf @method('PATCH')<button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-bold {{ $location->is_active ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}"><i class="bi bi-power" aria-hidden="true"></i> {{ $location->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>

<dialog id="location-dialog" aria-labelledby="location-dialog-title" class="w-[calc(100%-2rem)] max-w-2xl rounded-2xl border-0 p-0 text-slate-800 shadow-2xl">
    <div class="flex items-center justify-between border-b border-purple-100 bg-purple-50 px-6 py-4"><h2 id="location-dialog-title" class="font-bold">Add Business Location</h2><button type="button" data-close-location aria-label="Close form" class="rounded-lg p-1 text-slate-500 hover:bg-purple-100"><i class="bi bi-x-lg"></i></button></div>
    <form id="location-form" method="POST" action="{{ route('business.locations.store') }}">
        @csrf<input type="hidden" name="_method" value="POST"><input type="hidden" name="_location_id" value="{{ old('_location_id') }}">
        <div class="grid max-h-[70dvh] grid-cols-1 gap-4 overflow-y-auto p-6 sm:grid-cols-2">
            @if($errors->any())<div class="sm:col-span-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @foreach(['name' => 'Business Location', 'code' => 'Location ID', 'landmark' => 'Landmark', 'city' => 'City', 'zip_code' => 'Zip Code', 'state' => 'State', 'country' => 'Country', 'price_group' => 'Price Group'] as $field => $label)
                <div><label for="location-{{ $field }}" class="mb-1.5 block text-xs font-bold text-slate-700">{{ $label }}@if(in_array($field, ['name','code'])) <span class="text-rose-500">*</span>@endif</label><input id="location-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" maxlength="{{ $field === 'code' ? 100 : 255 }}" @if(in_array($field, ['name','code'])) required @endif class="{{ $fieldClass }}"></div>
            @endforeach
            <div class="sm:col-span-2 border-t border-slate-100 pt-3 text-sm font-bold text-purple-700" id="invoice-settings-heading">Invoice Settings</div>
            @foreach(['invoice_scheme' => 'Invoice scheme', 'invoice_layout_pos' => 'Invoice layout for POS', 'invoice_layout_sale' => 'Invoice layout for sale'] as $field => $label)
                <div><label for="location-{{ $field }}" class="mb-1.5 block text-xs font-bold text-slate-700">{{ $label }}</label><input id="location-{{ $field }}" name="{{ $field }}" value="{{ old($field, 'Default') }}" maxlength="255" required class="{{ $fieldClass }}"></div>
            @endforeach
        </div>
        <div class="flex justify-end gap-2 border-t border-purple-100 px-6 py-4"><button type="button" data-close-location class="rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-600">Close</button><button type="submit" class="rounded-xl bg-purple-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-purple-700">Save</button></div>
    </form>
</dialog>
@endsection
@push('scripts')
<style>
#location-dialog{margin:auto}#location-dialog::backdrop{background:rgb(15 23 42 / .5);backdrop-filter:blur(3px)}
#locations-table_wrapper .dataTables_filter,#locations-table_wrapper .dataTables_length{float:none;text-align:left}
#locations-table_wrapper .dataTables_filter input{min-width:180px}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('location-dialog');
    const form = document.getElementById('location-form');
    const title = document.getElementById('location-dialog-title');
    const fields = ['name','code','landmark','city','zip_code','state','country','price_group','invoice_scheme','invoice_layout_pos','invoice_layout_sale'];
    let previousFocus;
    function openDialog(button, settingsOnly = false) {
        previousFocus = document.activeElement;
        form.reset();
        const location = button ? JSON.parse(button.dataset.location) : null;
        form.action = button ? button.dataset.url : @json(route('business.locations.store'));
        form.elements._method.value = button ? 'PUT' : 'POST';
        form.elements._location_id.value = location?.id ?? '';
        fields.forEach(field => { form.elements[field].value = location?.[field] ?? (field.startsWith('invoice_') ? 'Default' : ''); });
        title.textContent = location ? (settingsOnly ? 'Location Settings: ' : 'Edit Business Location: ') + location.name : 'Add Business Location';
        dialog.showModal();
        if (settingsOnly) document.getElementById('location-invoice_scheme').focus();
        else form.elements.name.focus();
    }
    document.getElementById('add-location').addEventListener('click', () => openDialog(null));
    document.getElementById('locations-table').addEventListener('click', event => {
        const edit = event.target.closest('.edit-location, .settings-location');
        if (edit) openDialog(edit, edit.classList.contains('settings-location'));
    });
    document.querySelectorAll('[data-close-location]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => previousFocus?.focus());
    if (window.jQuery?.fn?.DataTable) {
        const table = $('#locations-table').DataTable({pageLength:25,order:[[0,'asc']],autoWidth:false,scrollX:false,columnDefs:[{targets:11,orderable:false,searchable:false}],dom:'<"flex flex-wrap items-center justify-between gap-4 mb-5"lf>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"ip>',language:{emptyTable:'No business locations yet. Add one to get started.',searchPlaceholder:'Search locations…'}});
        StickyDataTables.install(table);
    }
    @if($errors->any())
        const oldLocationId = @json(old('_location_id'));
        const oldButton = oldLocationId ? document.querySelector(`#locations-table .edit-location[data-url$="/${oldLocationId}"]`) : null;
        openDialog(oldButton);
        const oldValues = @json(old());
        fields.forEach(field => { if (Object.hasOwn(oldValues, field)) form.elements[field].value = oldValues[field] ?? ''; });
    @endif
});
</script>
@endpush
