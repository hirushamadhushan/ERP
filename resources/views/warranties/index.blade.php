@extends('layouts.app')
@section('title', 'Warranties')
@section('subtitle', 'Manage warranties')
@section('content')
@php
    $input = 'w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 text-slate-800 placeholder-slate-400 transition-all';
@endphp
@if(session('success'))
    <div role="status" class="mb-6 p-4 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm flex items-center gap-2">
        <i class="bi bi-check-circle-fill text-emerald-600"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-bold text-slate-900 flex items-baseline gap-2">
            Warranties
        </h1>
    </div>
</div>

<section class="bg-white rounded-2xl border border-purple-100 shadow-sm p-4 sm:p-6">
    <div class="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
        <h2 class="text-base font-bold text-slate-800">All Warranties</h2>
        <button id="add-warranty" type="button" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">
            <i class="bi bi-plus-lg text-sm" aria-hidden="true"></i> Add
        </button>
    </div>

    <div class="sticky-table-host">
        <table id="warranties-table" class="w-full text-left" style="width:100%">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Duration</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr>
                        <td class="font-medium text-slate-800">{{ $record->name }}</td>
                        <td class="whitespace-pre-wrap text-slate-600">{{ $record->description }}</td>
                        <td class="text-slate-600">{{ $record->formatted_duration }}</td>
                        <td>
                            <div class="flex items-center gap-2 whitespace-nowrap">
                                <button type="button" class="edit-warranty inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-bold transition-all" data-warranty="{{ json_encode($record->only(['id', 'name', 'description', 'duration', 'duration_type'])) }}" data-url="{{ route('products.warranties.update', $record) }}">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                                </button>
                                <form class="delete-warranty" method="POST" action="{{ route('products.warranties.destroy', $record) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-bold transition-all">
                                        <i class="bi bi-trash" aria-hidden="true"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<dialog id="warranty-dialog" aria-labelledby="warranty-title" class="bg-white shadow-2xl text-slate-800 rounded-2xl p-0 overflow-hidden border-0">
    <div class="flex justify-between items-center px-6 py-4 bg-purple-50 border-b border-purple-100">
        <h2 id="warranty-title" class="text-base font-bold text-slate-800">Add Warranty</h2>
        <button type="button" data-close-warranty aria-label="Close form" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-purple-100 flex items-center justify-center transition-colors">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <form id="warranty-form" method="POST" action="{{ old('_record_id') ? route('products.warranties.update', old('_record_id')) : route('products.warranties.store') }}">
        @csrf
        <input type="hidden" name="_method" value="{{ old('_record_id') ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_record_id" value="{{ old('_record_id') }}">

        <div class="p-6 space-y-4">
            @if($errors->any())
                <div id="warranty-errors" role="alert" class="p-3 rounded-xl bg-rose-50 text-rose-700 text-sm border border-rose-100">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="warranty-name" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Name:<span class="text-rose-500">*</span>
                </label>
                <input id="warranty-name" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="Name" class="{{ $input }}">
            </div>

            <div>
                <label for="warranty-description" class="block text-xs font-bold text-slate-700 mb-1.5">Description:</label>
                <textarea id="warranty-description" name="description" rows="3" maxlength="2000" placeholder="Description" class="{{ $input }}">{{ old('description') }}</textarea>
            </div>

            <div>
                <label for="warranty-duration" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Duration:<span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input id="warranty-duration" name="duration" type="number" min="1" max="9999" required value="{{ old('duration') }}" placeholder="Duration" class="{{ $input }}">
                    <select id="warranty-duration-type" name="duration_type" required class="{{ $input }}">
                        <option value="">Please Select</option>
                        <option value="days" @selected(old('duration_type') === 'days')>Days</option>
                        <option value="months" @selected(old('duration_type') === 'months')>Months</option>
                        <option value="years" @selected(old('duration_type') === 'years')>Years</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 px-6 py-4 border-t border-purple-100 bg-slate-50/50">
            <button id="save-warranty" type="submit" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">Save</button>
            <button type="button" data-close-warranty class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-bold transition-all">Close</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<style>
    #warranty-dialog { margin:auto; padding:0; border:0; border-radius:1rem; width:calc(100% - 2rem); max-width:34rem; max-height:calc(100dvh - 2rem); }
    #warranty-dialog::backdrop { background:rgb(15 23 42 / .5); backdrop-filter:blur(3px); }
    #warranties-table_wrapper .dt-buttons { display:flex; flex-wrap:wrap; gap:.25rem; }
    #warranties-table_wrapper .dataTables_filter, #warranties-table_wrapper .dataTables_length { float:none; text-align:left; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('warranty-dialog');
    const form = document.getElementById('warranty-form');
    const save = document.getElementById('save-warranty');
    let previousFocus;

    function openForm() { previousFocus = document.activeElement; save.disabled = false; save.textContent = 'Save'; dialog.showModal(); }

    document.getElementById('add-warranty')?.addEventListener('click', () => {
        form.reset();
        form.action = @json(route('products.warranties.store'));
        form.elements._method.value = 'POST';
        form.elements._record_id.value = '';
        ['_record_id', 'name', 'description', 'duration', 'duration_type'].forEach(key => { if (form.elements[key]) form.elements[key].value = ''; });
        document.getElementById('warranty-errors')?.remove();
        document.getElementById('warranty-title').textContent = 'Add Warranty';
        openForm();
    });

    document.getElementById('warranties-table')?.addEventListener('click', event => {
        const button = event.target.closest('.edit-warranty');
        if (!button) return;
        const warranty = JSON.parse(button.dataset.warranty);
        form.action = button.dataset.url;
        form.elements._method.value = 'PUT';
        form.elements._record_id.value = warranty.id;
        ['name', 'description', 'duration', 'duration_type'].forEach(key => {
            if (form.elements[key]) form.elements[key].value = warranty[key] ?? '';
        });
        document.getElementById('warranty-errors')?.remove();
        document.getElementById('warranty-title').textContent = 'Edit Warranty';
        openForm();
    });

    document.getElementById('warranties-table')?.addEventListener('submit', event => {
        if (event.target.matches('.delete-warranty') && !confirm('Delete this warranty? This cannot be undone.')) {
            event.preventDefault();
        }
    });

    document.querySelectorAll('[data-close-warranty]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => previousFocus?.focus());
    form.addEventListener('submit', () => { save.disabled = true; save.textContent = 'Saving…'; });

    if (@json($errors->any())) {
        document.getElementById('warranty-title').textContent = form.elements._record_id.value ? 'Edit Warranty' : 'Add Warranty';
        openForm();
    }

    if (window.jQuery && $.fn.DataTable) {
        const exportOptions = { columns:[0,1,2], format:{body: data => {const text = $('<div>').html(data).text().trim(); return /^[=+\-@\t\r]/.test(text) ? "'" + text : text;}} };
        const warrantiesTable = $('#warranties-table').DataTable({
            pageLength: 25,
            order: [[0, 'asc']],
            scrollX: false,
            autoWidth: false,
            columnDefs: [{ targets: 3, orderable: false, searchable: false }],
            dom: '<"flex flex-wrap items-center justify-between gap-4 mb-5"lBf>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"ip>',
            buttons: [
                { extend: 'csvHtml5', text: 'Export to CSV', exportOptions },
                { extend: 'excelHtml5', text: 'Export to Excel', exportOptions },
                { extend: 'print', text: 'Print', exportOptions },
                { extend: 'colvis', text: 'Column visibility', columns: [0, 1, 2, 3] },
                { extend: 'pdfHtml5', text: 'Export to PDF', exportOptions }
            ],
            language: { emptyTable: 'No data available in table', searchPlaceholder: 'Search…' }
        });
        StickyDataTables.install(warrantiesTable);
    }
});
</script>
@endpush
