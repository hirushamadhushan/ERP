@extends('layouts.app')
@section('title', 'Variations')
@section('subtitle', 'Manage product variations')
@section('content')
@php
    $input = 'w-full px-3.5 py-2 border border-slate-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 text-slate-800 placeholder-slate-400 transition-all';
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
            Variations
            <span class="text-xs font-medium text-slate-400">Manage product variations</span>
        </h1>
    </div>
</div>

<section class="bg-white rounded-2xl border border-purple-100 shadow-sm p-4 sm:p-6">
    <div class="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
        <h2 class="text-base font-bold text-slate-800">All variations</h2>
        <button id="add-variation" type="button" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">
            <i class="bi bi-plus-lg text-sm" aria-hidden="true"></i> Add
        </button>
    </div>

    <div class="sticky-table-host">
        <table id="variations-table" class="w-full text-left" style="width:100%">
            <thead>
                <tr>
                    <th>Variations</th>
                    <th>Values</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr>
                        <td class="font-medium text-slate-800">{{ $record->name }}</td>
                        <td class="text-slate-600">{{ implode(', ', $record->values ?? []) }}</td>
                        <td>
                            <div class="flex items-center gap-2 whitespace-nowrap">
                                <button type="button" class="edit-variation inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-bold transition-all" data-variation="{{ json_encode($record->only(['id', 'name', 'values'])) }}" data-url="{{ route('products.variations.update', $record) }}">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                                </button>
                                <form class="delete-variation" method="POST" action="{{ route('products.variations.destroy', $record) }}">
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

<dialog id="variation-dialog" aria-labelledby="variation-title" class="bg-white shadow-2xl text-slate-800 rounded-2xl p-0 overflow-hidden border-0">
    <div class="flex justify-between items-center px-6 py-4 bg-purple-50 border-b border-purple-100">
        <h2 id="variation-title" class="text-base font-bold text-slate-800">Add Variation</h2>
        <button type="button" data-close-variation aria-label="Close form" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-purple-100 flex items-center justify-center transition-colors">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <form id="variation-form" method="POST" action="{{ old('_record_id') ? route('products.variations.update', old('_record_id')) : route('products.variations.store') }}">
        @csrf
        <input type="hidden" name="_method" value="{{ old('_record_id') ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_record_id" value="{{ old('_record_id') }}">

        <div class="p-6 space-y-4">
            @if($errors->any())
                <div id="variation-errors" role="alert" class="p-3 rounded-xl bg-rose-50 text-rose-700 text-sm border border-rose-100">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="variation-name" class="block text-xs font-bold text-slate-700 mb-1.5">
                    Variation Name:<span class="text-rose-500">*</span>
                </label>
                <input id="variation-name" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="Variation Name" class="{{ $input }}">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Add variation values:<span class="text-rose-500">*</span>
                </label>
                <div id="variation-values-container" class="space-y-2.5">
                    <!-- Dynamic value rows injected here -->
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 px-6 py-4 border-t border-purple-100 bg-slate-50/50">
            <button id="save-variation" type="submit" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">Save</button>
            <button type="button" data-close-variation class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-bold transition-all">Close</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<style>
    #variation-dialog { margin:auto; padding:0; border:0; border-radius:1rem; width:calc(100% - 2rem); max-width:34rem; max-height:calc(100dvh - 2rem); }
    #variation-dialog::backdrop { background:rgb(15 23 42 / .5); backdrop-filter:blur(3px); }
    #variations-table_wrapper .dt-buttons { display:flex; flex-wrap:wrap; gap:.25rem; }
    #variations-table_wrapper .dataTables_filter, #variations-table_wrapper .dataTables_length { float:none; text-align:left; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('variation-dialog');
    const form = document.getElementById('variation-form');
    const save = document.getElementById('save-variation');
    const container = document.getElementById('variation-values-container');
    let previousFocus;

    function createValueRow(value = '', isFirst = false) {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 variation-value-row';
        row.innerHTML = `
            <input type="text" name="values[]" value="${value.replace(/"/g, '&quot;')}" placeholder="Variation value" class="${@json($input)}" ${isFirst ? 'required' : ''}>
            ${isFirst ? `
                <button type="button" class="add-value-btn shrink-0 w-9 h-9 rounded-xl bg-purple-600 hover:bg-purple-700 text-white flex items-center justify-center shadow-sm font-bold text-sm transition-all" title="Add another value">
                    <i class="bi bi-plus-lg"></i>
                </button>
            ` : `
                <button type="button" class="remove-value-btn shrink-0 w-9 h-9 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center font-bold text-sm transition-all" title="Remove value">
                    <i class="bi bi-x-lg"></i>
                </button>
            `}
        `;
        return row;
    }

    function renderValues(values = []) {
        container.innerHTML = '';
        if (values.length === 0) {
            container.appendChild(createValueRow('', true));
        } else {
            values.forEach((val, idx) => {
                container.appendChild(createValueRow(val, idx === 0));
            });
        }
    }

    container.addEventListener('click', (e) => {
        const addBtn = e.target.closest('.add-value-btn');
        if (addBtn) {
            container.appendChild(createValueRow('', false));
            const lastInput = container.querySelector('.variation-value-row:last-child input');
            if (lastInput) lastInput.focus();
            return;
        }
        const removeBtn = e.target.closest('.remove-value-btn');
        if (removeBtn) {
            const row = removeBtn.closest('.variation-value-row');
            if (row && container.children.length > 1) {
                row.remove();
            }
        }
    });

    function openForm() { previousFocus = document.activeElement; save.disabled = false; save.textContent = 'Save'; dialog.showModal(); }

    document.getElementById('add-variation')?.addEventListener('click', () => {
        form.reset();
        form.action = @json(route('products.variations.store'));
        form.elements._method.value = 'POST';
        form.elements._record_id.value = '';
        renderValues(['']);
        document.getElementById('variation-errors')?.remove();
        document.getElementById('variation-title').textContent = 'Add Variation';
        openForm();
    });

    document.getElementById('variations-table')?.addEventListener('click', event => {
        const button = event.target.closest('.edit-variation');
        if (!button) return;
        const variation = JSON.parse(button.dataset.variation);
        form.action = button.dataset.url;
        form.elements._method.value = 'PUT';
        form.elements._record_id.value = variation.id;
        form.elements.name.value = variation.name ?? '';
        renderValues(variation.values && variation.values.length ? variation.values : ['']);
        document.getElementById('variation-errors')?.remove();
        document.getElementById('variation-title').textContent = 'Edit Variation';
        openForm();
    });

    document.getElementById('variations-table')?.addEventListener('submit', event => {
        if (event.target.matches('.delete-variation') && !confirm('Delete this variation? This cannot be undone.')) {
            event.preventDefault();
        }
    });

    document.querySelectorAll('[data-close-variation]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => previousFocus?.focus());
    form.addEventListener('submit', () => { save.disabled = true; save.textContent = 'Saving…'; });

    if (@json($errors->any())) {
        document.getElementById('variation-title').textContent = form.elements._record_id.value ? 'Edit Variation' : 'Add Variation';
        openForm();
    }

    if (window.jQuery && $.fn.DataTable) {
        const exportOptions = { columns:[0,1], format:{body: data => {const text = $('<div>').html(data).text().trim(); return /^[=+\-@\t\r]/.test(text) ? "'" + text : text;}} };
        const variationsTable = $('#variations-table').DataTable({
            pageLength: 25,
            order: [[0, 'asc']],
            scrollX: false,
            autoWidth: false,
            columnDefs: [{ targets: 2, orderable: false, searchable: false }],
            dom: '<"flex flex-wrap items-center justify-between gap-4 mb-5"lBf>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"ip>',
            buttons: [
                { extend: 'csvHtml5', text: 'Export to CSV', exportOptions },
                { extend: 'excelHtml5', text: 'Export to Excel', exportOptions },
                { extend: 'print', text: 'Print', exportOptions },
                { extend: 'colvis', text: 'Column visibility', columns: [0, 1, 2] },
                { extend: 'pdfHtml5', text: 'Export to PDF', exportOptions }
            ],
            language: { emptyTable: 'No data available in table', searchPlaceholder: 'Search…' }
        });
        StickyDataTables.install(variationsTable);
    }
});
</script>
@endpush
