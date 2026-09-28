@extends('layouts.app')
@section('title', ucfirst($kind))
@section('subtitle', 'Manage your '.$kind)
@section('content')
@php
    $isCategory = $kind === 'categories';
    $prefix = 'products.'.$kind;
    $input = 'w-full px-3.5 py-2 border border-slate-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 text-slate-800 placeholder-slate-400 transition-all';
@endphp
<div id="reference-toast" class="reference-toast hidden" role="status"><div class="flex items-center gap-3"><i id="reference-toast-icon" class="bi bi-shield-exclamation text-2xl"></i><p id="reference-toast-message" class="m-0 leading-5"></p></div></div>

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

    @if($isCategory)
        @include('products.partials.category-tree')
    @else
    <div class="sticky-table-host">
        <table data-async-table id="reference-table" class="w-full text-left" style="width:100%">
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
                    <tr @if($isCategory) data-category-id="{{ $record->id }}" data-parent-id="{{ $record->parent_id }}" @endif>
                        <td class="font-medium text-slate-800">
                        @if($isCategory)
                            <div class="category-branch" style="padding-left:{{ $record->tree_depth * 22 }}px">
                            @if($records->contains('parent_id', $record->id))<button type="button" class="tree-toggle" aria-expanded="true" aria-label="Toggle {{ $record->name }}">▾</button>@else<span class="tree-leaf" aria-hidden="true">└</span>@endif
                            <span>{{ $record->name }}</span>
                            @if($record->tree_depth < \App\Models\Category::MAX_DEPTH)<button type="button" class="add-child" data-parent="{{ $record->id }}" aria-label="Add sub-category to {{ $record->name }}" title="Add sub-category">+</button>@endif
                            </div>
                        @else {{ $record->name }} @endif
                        </td>
                        @if($isCategory)<td class="text-slate-600">{{ $record->code }}</td>@endif
                        <td class="whitespace-pre-wrap text-slate-600">{{ $record->description }}</td>
                        <td>
                            <div class="flex items-center gap-2 whitespace-nowrap">
                                <button type="button" class="edit-reference inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-bold transition-all" data-record="{{ json_encode($record->only(['id', 'name', 'code', 'description', 'parent_id'])) }}" data-url="{{ route($prefix.'.update', $record) }}">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                                </button>
                                <form data-async-form class="delete-reference" method="POST" action="{{ route($prefix.'.destroy', $record) }}">
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
    @endif
</section>

<dialog id="reference-dialog" aria-labelledby="reference-title" class="bg-white shadow-2xl text-slate-800 rounded-2xl p-0 overflow-hidden border-0">
    <div class="flex justify-between items-center px-6 py-4 bg-purple-50 border-b border-purple-100">
        <h2 id="reference-title" class="text-base font-bold text-slate-800">Add {{ $singular }}</h2>
        <button type="button" data-close-reference aria-label="Close form" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-purple-100 flex items-center justify-center transition-colors">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <form data-async-form id="reference-form" method="POST" action="{{ old('_record_id') ? route($prefix.'.update', old('_record_id')) : route($prefix.'.store') }}">
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
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="is-subcategory" @checked(old('parent_id')) style="accent-color:#9333ea"> Add as sub-category</label>
                <div id="parent-category-field" hidden>
                    <label for="parent-category" class="block text-xs font-bold mb-2">Select parent category *</label>
                    <select data-async-options id="parent-category" name="parent_id" class="{{ $input }}">
                        <option value="">Please select</option>
                        @foreach($records as $parent)
                        <option value="{{ $parent->id }}" data-parent="{{ $parent->parent_id }}" data-depth="{{ $parent->tree_depth }}" @selected(old('parent_id') == $parent->id)>{{ $parent->tree_path }}</option>
                        @endforeach
                    </select>
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
    .category-branch{display:flex;align-items:center;gap:8px;min-height:38px}.tree-toggle,.tree-leaf{width:24px;flex-shrink:0;color:#9333ea}.add-child{width:26px;height:26px;border-radius:7px;background:#faf5ff;color:#9333ea;font-weight:700}.add-child:hover{background:#f3e8ff}
    #reference-table tr[hidden]{display:none}#reference-table td,#reference-table th{padding:10px;border-bottom:1px solid #f1f5f9}
    #reference-dialog { margin:auto; padding:0; border:0; border-radius:1rem; width:calc(100% - 2rem); max-width:32rem; max-height:calc(100dvh - 2rem); }
    #reference-dialog::backdrop { background:rgb(15 23 42 / .5); backdrop-filter:blur(3px); }
    #reference-table_wrapper .dt-buttons { display:flex; flex-wrap:wrap; gap:.25rem; }
    #reference-table_wrapper .dataTables_filter, #reference-table_wrapper .dataTables_length { float:none; text-align:left; }
    .reference-toast{position:fixed;right:1.25rem;top:.75rem;z-index:90;width:300px;padding:1rem;border-radius:.375rem;background:#c9534f;color:#fff;font-size:.875rem;font-weight:600;box-shadow:0 12px 28px rgb(15 23 42 / .22)}
    .reference-toast.success{background:#059669}.reference-toast.hidden{display:none}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('reference-toast');
    const showReferenceToast = (message, type = 'error') => {
        document.getElementById('reference-toast-message').textContent = message;
        document.getElementById('reference-toast-icon').className = type === 'success' ? 'bi bi-check-circle-fill text-2xl' : 'bi bi-shield-exclamation text-2xl';
        toast.classList.toggle('success', type === 'success');
        toast.classList.remove('hidden');
        window.clearTimeout(window.referenceToastTimer);
        window.referenceToastTimer = window.setTimeout(() => toast.classList.add('hidden'), 4000);
    };
    @if(session('success')) showReferenceToast(@json(session('success')), 'success'); @endif
    @if($isCategory && session('category_error')) showReferenceToast(@json(session('category_error'))); @endif
    const dialog = document.getElementById('reference-dialog'), form = document.getElementById('reference-form'), save = document.getElementById('save-reference');
    const singular = @json($singular);
    let previousFocus;
    const subcategory = document.getElementById('is-subcategory'), parentSelect = document.getElementById('parent-category');
    function syncParent() {
        if (!subcategory) return;
        document.getElementById('parent-category-field').hidden = !subcategory.checked;
        parentSelect.disabled = !subcategory.checked;
        parentSelect.required = subcategory.checked;
        const editing = form.elements._record_id.value;
        Array.from(parentSelect.options).forEach(option => {
            let cursor = option, excluded = Number(option.dataset.depth) >= 5;
            while (cursor?.value) {
                if (editing && cursor.value === editing) { excluded = true; break; }
                cursor = Array.from(parentSelect.options).find(candidate => candidate.value === cursor.dataset.parent);
            }
            option.disabled = excluded;
        });
    }
    let categoryRows = Array.from(document.querySelectorAll('[data-category-id]'));
    let categoryRowMap = new Map(categoryRows.map(row => [row.dataset.categoryId, row]));
    document.addEventListener('app:content-updated', () => {
        categoryRows = Array.from(document.querySelectorAll('[data-category-id]'));
        categoryRowMap = new Map(categoryRows.map(row => [row.dataset.categoryId, row]));
        refreshCategoryTree();
    });
    function drawCategoryLines() {
        const visibleRows = categoryRows.filter(row => !row.hidden);
        const siblingGroups = new Map();
        visibleRows.forEach(row => {
            const key = row.dataset.parentId;
            if (!siblingGroups.has(key)) siblingGroups.set(key, []);
            siblingGroups.get(key).push(row);
        });
        const hasNextSibling = row => siblingGroups.get(row.dataset.parentId)?.at(-1) !== row;
        visibleRows.forEach(row => {
            const cell = row.querySelector('.category-tree-cell'), svg = row.querySelector('.category-lines');
            if (!svg) return;
            const node = row.querySelector('.category-node');
            const cellRect = cell.getBoundingClientRect(), nodeRect = node.getBoundingClientRect();
            const height = cellRect.height, middle = nodeRect.top - cellRect.top + nodeRect.height / 2;
            const depth = Number(node.style.getPropertyValue('--depth'));
            const x = level => 14 + level * 24 + 11;
            let path = '';
            if (depth) {
                path += 'M '+x(depth-1)+' -1 V '+middle+' H '+x(depth);
                if (hasNextSibling(row)) path += ' M '+x(depth-1)+' '+middle+' V '+(height+1);
                let ancestor = categoryRowMap.get(row.dataset.parentId), level = depth - 1;
                while (ancestor && level > 0) {
                    if (hasNextSibling(ancestor)) path += ' M '+x(level-1)+' -1 V '+(height+1);
                    ancestor = categoryRowMap.get(ancestor.dataset.parentId);
                    level--;
                }
            }
            if (siblingGroups.has(row.dataset.categoryId)) path += ' M '+x(depth)+' '+middle+' V '+(height+1);
            svg.replaceChildren();
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            line.setAttribute('d', path);
            svg.appendChild(line);
        });
    }
    function refreshCategoryTree() {
        const term = (document.getElementById('category-search')?.value || '').trim().toLowerCase();
        const visible = new Set();
        if (term) {
            categoryRows.forEach(row => {
                if (!row.dataset.search.includes(term)) return;
                let cursor = row;
                while (cursor) { visible.add(cursor.dataset.categoryId); cursor = categoryRowMap.get(cursor.dataset.parentId); }
            });
        }
        categoryRows.forEach(row => {
            const parent = categoryRowMap.get(row.dataset.parentId);
            row.hidden = term ? !visible.has(row.dataset.categoryId) : !!parent && (parent.hidden || parent.querySelector('.tree-toggle')?.getAttribute('aria-expanded') === 'false');
        });
        const empty = document.getElementById('category-empty');
        if (empty) empty.hidden = categoryRows.some(row => !row.hidden);
        requestAnimationFrame(drawCategoryLines);
    }
    if (categoryRows.length) {
        requestAnimationFrame(drawCategoryLines);
        new ResizeObserver(() => requestAnimationFrame(drawCategoryLines)).observe(document.getElementById('reference-table'));
    }
    document.getElementById('category-search')?.addEventListener('input', refreshCategoryTree);
    for (const [id, expanded] of [['expand-categories', true], ['collapse-categories', false]]) {
        document.getElementById(id)?.addEventListener('click', () => {
            document.getElementById('category-search').value = '';
            document.querySelectorAll('.tree-toggle').forEach(toggle => {
                toggle.setAttribute('aria-expanded', String(expanded));
                toggle.innerHTML = expanded ? '<i class="bi bi-chevron-down"></i>' : '<i class="bi bi-chevron-right"></i>';
            });
            refreshCategoryTree();
        });
    }
    subcategory?.addEventListener('change', syncParent);
    syncParent();
    function openForm() { previousFocus = document.activeElement; save.disabled = false; save.textContent = 'Save'; dialog.showModal(); }
    
    document.querySelectorAll('#add-reference').forEach(btn => {
        btn.addEventListener('click', () => {
            form.reset(); form.action = @json(route($prefix.'.store')); form.elements._method.value = 'POST';
        ['_record_id', 'name', 'code', 'description'].forEach(key => {if(form.elements[key]) form.elements[key].value = '';});
            if (subcategory) { subcategory.checked = false; parentSelect.value = ''; syncParent(); }
            document.getElementById('reference-errors')?.remove(); document.getElementById('reference-title').textContent = 'Add ' + singular.toLowerCase(); openForm();
        });
    });

    document.getElementById('reference-table')?.addEventListener('click', event => {
        const child = event.target.closest('.add-child');
        if (child) { document.getElementById('add-reference').click(); subcategory.checked = true; parentSelect.value = child.dataset.parent; syncParent(); return; }
        const toggle = event.target.closest('.tree-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', toggle.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
            toggle.innerHTML = toggle.getAttribute('aria-expanded') === 'true' ? '<i class="bi bi-chevron-down"></i>' : '<i class="bi bi-chevron-right"></i>';
            refreshCategoryTree();
            return;
        }
        const button = event.target.closest('.edit-reference'); if (!button) return;
        const record = JSON.parse(button.dataset.record);
        form.action = button.dataset.url; form.elements._method.value = 'PUT'; form.elements._record_id.value = record.id;
        ['name', 'code', 'description'].forEach(key => {if(form.elements[key]) form.elements[key].value = record[key] ?? '';});
        if (subcategory) { subcategory.checked = !!record.parent_id; parentSelect.value = record.parent_id ?? ''; syncParent(); }
        document.getElementById('reference-errors')?.remove(); document.getElementById('reference-title').textContent = 'Edit ' + singular.toLowerCase(); openForm();
    });

    document.getElementById('reference-table')?.addEventListener('submit', event => {
        if (event.target.matches('.delete-reference') && !confirm('Delete this ' + singular.toLowerCase() + '? This cannot be undone.')) event.preventDefault();
    });

    document.querySelectorAll('[data-close-reference]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => previousFocus?.focus());

    
    if (@json($errors->any())) {
        document.getElementById('reference-title').textContent = (form.elements._record_id.value ? 'Edit ' : 'Add ') + singular.toLowerCase();
        openForm();
    }

    if (!@json($isCategory) && window.jQuery && $.fn.DataTable) {
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
