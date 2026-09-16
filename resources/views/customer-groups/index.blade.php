@extends('layouts.app')
@section('title', 'Customer Groups')
@section('subtitle', 'Manage customer groups')
@section('content')
@php
    $inputClass = 'w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-500';
    $buttonClass = 'inline-flex items-center gap-2 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-md shadow-purple-500/20 transition-colors';
@endphp
@if(session('success'))<div role="status" class="mb-6 p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm">{{ session('success') }}</div>@endif
@if(session('group_error'))<div role="alert" class="mb-6 p-4 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-sm">{{ session('group_error') }}</div>@endif
<section class="bg-white rounded-2xl border border-purple-100 shadow-sm p-4 sm:p-6">
    <div class="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
        <h1 class="text-lg font-bold text-slate-900">All Customer Groups</h1>
        <button id="add-group" type="button" class="{{ $buttonClass }}"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add</button>
    </div>
    <div class="sticky-table-host">
        <table id="groups-table" class="w-full text-left" style="width:100%">
            <thead><tr><th>Customer Group Name</th><th>Calculation Percentage (%)</th><th>Selling Price Group</th><th>Action</th></tr></thead>
            <tbody>
                @foreach($groups as $group)
                    <tr>
                        <td>{{ $group->name }}</td><td>{{ $group->calculation_type === 'percentage' ? $group->calculation_percentage : '—' }}</td><td>{{ $group->selling_price_group ?? '—' }}</td>
                        <td><div class="flex items-center gap-2 whitespace-nowrap">
                            <button type="button" class="edit-group px-3 py-1.5 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-bold" data-id="{{ $group->id }}" data-name="{{ $group->name }}" data-type="{{ $group->calculation_type }}" data-price-group="{{ $group->selling_price_group }}" data-percentage="{{ $group->calculation_percentage }}" data-url="{{ route('contacts.groups.update', $group) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</button>
                            <form method="POST" action="{{ route('contacts.groups.destroy', $group) }}" class="delete-group">@csrf @method('DELETE')<button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-bold"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button></form>
                        </div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
<dialog id="group-dialog" aria-labelledby="group-dialog-title" class="bg-white text-slate-800 shadow-2xl">
    <div class="flex items-center justify-between px-6 py-4 bg-purple-50 border-b border-purple-100">
        <h2 id="group-dialog-title" class="text-base font-bold">Add Customer Group</h2>
        <button type="button" data-close-group aria-label="Close customer group form" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-purple-100"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <form id="group-form" method="POST" action="{{ old('_group_id') ? route('contacts.groups.update', old('_group_id')) : route('contacts.groups.store') }}">
        @csrf
        <input name="_method" type="hidden" value="{{ old('_group_id') ? 'PUT' : 'POST' }}">
        <input name="_group_id" type="hidden" value="{{ old('_group_id') }}">
        <div class="p-6 space-y-5">
            @if($errors->any())<div id="group-errors" role="alert" class="rounded-xl bg-rose-50 text-rose-700 p-3 text-sm"><ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div><label for="group-name" class="block text-xs font-bold mb-1.5">Customer Group Name <span class="text-rose-500">*</span></label><input id="group-name" name="name" value="{{ old('name') }}" required maxlength="255" class="{{ $inputClass }}" placeholder="Customer Group Name"></div>
            <div><label for="group-type" class="block text-xs font-bold mb-1.5">Price calculation type</label><select id="group-type" name="calculation_type" class="{{ $inputClass }}"><option value="percentage" @selected(old('calculation_type', 'percentage') === 'percentage')>Percentage</option><option value="selling_price_group" @selected(old('calculation_type') === 'selling_price_group')>Selling Price Group</option></select></div>
            <div id="percentage-fields">
                <div class="relative flex items-center gap-1 mb-1.5">
                    <label for="group-percentage" class="text-xs font-bold">Calculation Percentage (%)</label>
                    <span class="percentage-info inline-flex">
                        <button id="percentage-info-button" type="button" aria-label="How calculation percentage works" aria-describedby="percentage-tooltip" class="text-purple-500 w-6 h-6 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></button>
                        <span id="percentage-tooltip" role="tooltip" class="percentage-tooltip text-xs text-slate-700 bg-white border border-slate-200 rounded-lg shadow-xl p-4">
                            <strong class="block mb-3">Selling price = Selling price set for the product + Calculation percentage</strong>
                            You can specify the percentage as positive to increase and negative to decrease the selling price.
                        </span>
                    </span>
                </div>
                <input id="group-percentage" name="calculation_percentage" type="number" min="-100" max="100" step="0.01" required value="{{ old('calculation_percentage', 0) }}" aria-describedby="percentage-tooltip" class="{{ $inputClass }}" placeholder="Calculation Percentage (%)">
            </div>
            <div id="selling-price-fields" hidden><label for="group-selling-price" class="block text-xs font-bold mb-1.5">Selling Price Group <span class="text-rose-500">*</span></label><input id="group-selling-price" name="selling_price_group" maxlength="255" value="{{ old('selling_price_group') }}" class="{{ $inputClass }}" placeholder="Selling Price Group name" list="selling-price-names"><datalist id="selling-price-names">@foreach($groups->pluck('selling_price_group')->filter()->unique() as $priceGroup)<option value="{{ $priceGroup }}">@endforeach</datalist></div>
        </div>
        <div class="flex justify-end gap-2 px-6 py-4 border-t border-purple-100">
            <button id="save-group" type="submit" class="{{ $buttonClass }}">Save</button>
            <button type="button" data-close-group class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-600">Close</button>
        </div>
    </form>
</dialog>
@endsection
@push('scripts')
<style>
    #group-dialog { border: 0; margin: auto; padding: 0; width: calc(100% - 2rem); max-width: 38rem; max-height: calc(100dvh - 2rem); border-radius: 1rem; }
    #group-dialog::backdrop { background: rgb(15 23 42 / .5); backdrop-filter: blur(3px); }
    .percentage-tooltip { display: none; position: absolute; z-index: 20; bottom: 100%; left: 0; width: 17rem; max-width: 100%; box-sizing: border-box; overflow-wrap: anywhere; line-height: 1.5; }
    .percentage-info:hover .percentage-tooltip, .percentage-info:focus-within .percentage-tooltip { display: block; }
    .percentage-info.tooltip-dismissed .percentage-tooltip { display: none; }
    #groups-table_wrapper .dt-buttons { display: flex; flex-wrap: wrap; gap: .25rem; }
    #groups-table_wrapper .dataTables_filter, #groups-table_wrapper .dataTables_length { float: none; text-align: left; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('group-dialog');
    const form = document.getElementById('group-form');
    const title = document.getElementById('group-dialog-title');
    const save = document.getElementById('save-group');
    const storeUrl = @json(route('contacts.groups.store'));
    let previousFocus;
    function updateCalculationFields() {
        const percentage = form.elements.calculation_type.value === 'percentage';
        document.getElementById('percentage-fields').hidden = !percentage;
        document.getElementById('selling-price-fields').hidden = percentage;
        form.elements.calculation_percentage.disabled = !percentage;
        form.elements.calculation_percentage.required = percentage;
        form.elements.selling_price_group.disabled = percentage;
        form.elements.selling_price_group.required = !percentage;
    }
    form.elements.calculation_type.addEventListener('change', updateCalculationFields);
    const info = document.querySelector('.percentage-info');
    info.addEventListener('mouseenter', () => info.classList.remove('tooltip-dismissed'));
    info.addEventListener('focusin', () => info.classList.remove('tooltip-dismissed'));
    dialog.addEventListener('keydown', event => {
        if (event.key === 'Escape' && getComputedStyle(document.getElementById('percentage-tooltip')).display !== 'none') {
            event.preventDefault();
            info.classList.add('tooltip-dismissed');
        }
    });
    function openGroup() {
        previousFocus = document.activeElement;
        save.disabled = false;
        save.textContent = 'Save';
        updateCalculationFields();
        dialog.showModal();
    }
    document.getElementById('add-group').addEventListener('click', () => {
        form.reset();
        form.action = storeUrl;
        form.elements._method.value = 'POST';
        form.elements._group_id.value = '';
        form.elements.name.value = '';
        form.elements.calculation_type.value = 'percentage';
        form.elements.selling_price_group.value = '';
        form.elements.calculation_percentage.value = '0';
        title.textContent = 'Add Customer Group';
        document.getElementById('group-errors')?.remove();
        openGroup();
    });
    document.getElementById('groups-table').addEventListener('click', event => {
        const button = event.target.closest('.edit-group');
        if (!button) return;
        form.action = button.dataset.url;
        form.elements._method.value = 'PUT';
        form.elements._group_id.value = button.dataset.id;
        form.elements.name.value = button.dataset.name;
        form.elements.calculation_percentage.value = button.dataset.percentage;
        form.elements.calculation_type.value = button.dataset.type;
        form.elements.selling_price_group.value = button.dataset.priceGroup || '';
        title.textContent = 'Edit Customer Group';
        document.getElementById('group-errors')?.remove();
        openGroup();
    });
    document.getElementById('groups-table').addEventListener('submit', event => {
        if (event.target.matches('.delete-group') && !confirm('Delete this customer group? This cannot be undone.')) event.preventDefault();
    });
    document.querySelectorAll('[data-close-group]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => previousFocus?.focus());
    form.addEventListener('submit', () => { save.disabled = true; save.textContent = 'Saving…'; });
    if (@json($errors->any())) {
        title.textContent = form.elements._group_id.value ? 'Edit Customer Group' : 'Add Customer Group';
        openGroup();
    }
    if (window.jQuery && $.fn.DataTable) {
        const exportOptions = { columns: [0, 1, 2], format: { body: data => {
            const value = $('<div>').html(data).text().trim();
            return /^[=+\-@\t\r]/.test(value) ? "'" + value : value;
        } } };
        const groupsTable = $('#groups-table').DataTable({
            pageLength: 25, order: [[0, 'asc']], scrollX: false, autoWidth: false,
            columnDefs: [{ targets: 3, searchable: false, orderable: false }],
            dom: '<"flex flex-wrap items-center justify-between gap-4 mb-5"lBf>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"ip>',
            buttons: [
                { extend: 'csvHtml5', text: 'Export to CSV', exportOptions },
                { extend: 'excelHtml5', text: 'Export to Excel', exportOptions },
                { extend: 'print', text: 'Print', exportOptions },
                { extend: 'colvis', text: 'Column visibility', columns: [0, 1, 2] },
                { extend: 'pdfHtml5', text: 'Export to PDF', exportOptions }
            ],
            language: { emptyTable: 'No customer groups yet. Add a group to get started.', searchPlaceholder: 'Search groups…' }
        });
        StickyDataTables.install(groupsTable);
    }
});
</script>
@endpush
