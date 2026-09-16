@extends('layouts.app')
@section('title', ucfirst($kind))
@section('subtitle', 'Manage your '.$kind)
@section('content')
@php
    $isCategory = $kind === 'categories';
    $prefix = 'products.'.$kind;
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
            {{ ucfirst($kind) }}
            <span class="text-xs font-medium text-slate-400">Manage your {{ strtolower($kind) }}</span>
        </h1>
    </div>
    @if($isCategory)
        <button id="add-reference" type="button" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">
            <i class="bi bi-plus-lg text-sm" aria-hidden="true"></i> Add
        </button>
    @endif
</div>

<section class="bg-white rounded-2xl border border-purple-100 shadow-sm p-4 sm:p-6">
    @if(!$isCategory)
        <div class="flex items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-800">All your brands</h2>
            <button id="add-reference" type="button" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">
                <i class="bi bi-plus-lg text-sm" aria-hidden="true"></i> Add
            </button>
        </div>
    @endif

    <div class="sticky-table-host">
        <table id="reference-table" class="w-full text-left" style="width:100%">
            <thead>
                <tr>
                    <th>{{ $isCategory ? 'Category' : 'Brands' }}</th>
                    @if($isCategory)<th>Category Code</th>@endif
                    <th>{{ $isCategory ? 'Description' : 'Note' }}</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr>
                        <td class="font-medium text-slate-800">{{ $record->name }}</td>
                        @if($isCategory)<td class="text-slate-600">{{ $record->code }}</td>@endif
                        <td class="whitespace-pre-wrap text-slate-600">{{ $record->description }}</td>
                        <td>
                            <div class="flex items-center gap-2 whitespace-nowrap">
                                <button type="button" class="edit-reference inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-bold transition-all" data-record="{{ json_encode($record->only(['id', 'name', 'code', 'description'])) }}" data-url="{{ route($prefix.'.update', $record) }}">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                                </button>
                                <form class="delete-reference" method="POST" action="{{ route($prefix.'.destroy', $record) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-bold transition-all">
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

<dialog id="reference-dialog" aria-labelledby="reference-title" class="bg-white shadow-2xl text-slate-800 rounded-2xl p-0 overflow-hidden border-0">
    <div class="flex justify-between items-center px-6 py-4 bg-purple-50 border-b border-purple-100">
        <h2 id="reference-title" class="text-base font-bold text-slate-800">Add {{ $singular }}</h2>
        <button type="button" data-close-reference aria-label="Close form" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-purple-100 flex items-center justify-center transition-colors">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <form id="reference-form" method="POST" action="{{ old('_record_id') ? route($prefix.'.update', old('_record_id')) : route($prefix.'.store') }}">
        @csrf
        <input type="hidden" name="_method" value="{{ old('_record_id') ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_record_id" value="{{ old('_record_id') }}">
        
        <div class="p-6 space-y-4">
            @if($errors->any())
                <div id="reference-errors" role="alert" class="p-3 rounded-xl bg-rose-50 text-rose-700 text-sm border border-rose-100">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="reference-name" class="block text-xs font-bold text-slate-700 mb-1.5">
                    {{ $singular }} name <span class="text-rose-500">*</span>
                </label>
                <input id="reference-name" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="{{ $singular }} name" class="{{ $input }}">
            </div>

            @if($isCategory)
                <div>
                    <label for="reference-code" class="block text-xs font-bold text-slate-700 mb-1.5">Category Code</label>
                    <input id="reference-code" name="code" maxlength="50" value="{{ old('code') }}" placeholder="Category Code" aria-describedby="category-code-help" class="{{ $input }}">
                    <p id="category-code-help" class="text-xs text-slate-400 mt-1.5">Category code is same as <strong>HSN code</strong></p>
                </div>
                <div>
                    <label for="reference-description" class="block text-xs font-bold text-slate-700 mb-1.5">Description</label>
                    <textarea id="reference-description" name="description" rows="3" maxlength="2000" placeholder="Description" class="{{ $input }}">{{ old('description') }}</textarea>
                </div>
            @else
                <div>
                    <label for="reference-description" class="block text-xs font-bold text-slate-700 mb-1.5">Short description</label>
                    <input id="reference-description" name="description" maxlength="255" value="{{ old('description') }}" placeholder="Short description" class="{{ $input }}">
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-2 px-6 py-4 border-t border-purple-100 bg-slate-50/50">
            <button id="save-reference" type="submit" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">Save</button>
            <button type="button" data-close-reference class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-bold transition-all">Close</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<style>
    #reference-dialog { margin:auto; padding:0; border:0; border-radius:1rem; width:calc(100% - 2rem); max-width:32rem; max-height:calc(100dvh - 2rem); }
    #reference-dialog::backdrop { background:rgb(15 23 42 / .5); backdrop-filter:blur(3px); }
    #reference-table_wrapper .dt-buttons { display:flex; flex-wrap:wrap; gap:.25rem; }
    #reference-table_wrapper .dataTables_filter, #reference-table_wrapper .dataTables_length { float:none; text-align:left; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('reference-dialog'), form = document.getElementById('reference-form'), save = document.getElementById('save-reference');
    const singular = @json($singular);
    let previousFocus;
    function openForm() { previousFocus = document.activeElement; save.disabled = false; save.textContent = 'Save'; dialog.showModal(); }
    
    document.querySelectorAll('#add-reference').forEach(btn => {
        btn.addEventListener('click', () => {
            form.reset(); form.action = @json(route($prefix.'.store')); form.elements._method.value = 'POST';
            ['_record_id', 'name', 'code', 'description'].forEach(key => {if(form.elements[key]) form.elements[key].value = '';});
            document.getElementById('reference-errors')?.remove(); document.getElementById('reference-title').textContent = 'Add ' + singular.toLowerCase(); openForm();
        });
    });

    document.getElementById('reference-table')?.addEventListener('click', event => {
        const button = event.target.closest('.edit-reference'); if (!button) return;
        const record = JSON.parse(button.dataset.record);
        form.action = button.dataset.url; form.elements._method.value = 'PUT'; form.elements._record_id.value = record.id;
        ['name', 'code', 'description'].forEach(key => {if(form.elements[key]) form.elements[key].value = record[key] ?? '';});
        document.getElementById('reference-errors')?.remove(); document.getElementById('reference-title').textContent = 'Edit ' + singular.toLowerCase(); openForm();
    });

    document.getElementById('reference-table')?.addEventListener('submit', event => {
        if (event.target.matches('.delete-reference') && !confirm('Delete this ' + singular.toLowerCase() + '? This cannot be undone.')) event.preventDefault();
    });

    document.querySelectorAll('[data-close-reference]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => previousFocus?.focus());
    form.addEventListener('submit', () => { save.disabled = true; save.textContent = 'Saving…'; });
    
    if (@json($errors->any())) {
        document.getElementById('reference-title').textContent = (form.elements._record_id.value ? 'Edit ' : 'Add ') + singular.toLowerCase();
        openForm();
    }

    if (window.jQuery && $.fn.DataTable) {
        const actionColumn = @json($isCategory ? 3 : 2);
        const exportOptions = {columns: Array.from({length:actionColumn}, (_,i) => i),format:{body: data => {const text = $('<div>').html(data).text().trim();return /^[=+\-@\t\r]/.test(text) ? "'" + text : text;}}};
        const referenceTable = $('#reference-table').DataTable({pageLength:25,order:[[0,'asc']],scrollX:false,autoWidth:false,
            columnDefs:[{targets:actionColumn,orderable:false,searchable:false}],
            dom:'<"flex flex-wrap items-center justify-between gap-4 mb-5"lBf>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"ip>',
            buttons:[{extend:'csvHtml5',text:'Export to CSV',exportOptions},{extend:'excelHtml5',text:'Export to Excel',exportOptions},{extend:'print',text:'Print',exportOptions},{extend:'colvis',text:'Column visibility'},{extend:'pdfHtml5',text:'Export to PDF',exportOptions}],
            language:{emptyTable:'No data available in table',searchPlaceholder:'Search…'}
        });
        StickyDataTables.install(referenceTable);
    }
});
</script>
@endpush
