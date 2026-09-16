@extends('layouts.app')
@section('title', 'Units')
@section('subtitle', 'Manage your units')
@section('content')
@php
    $input = 'w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 text-slate-800 placeholder-slate-400 transition-all';
@endphp
@if(session('success'))<div role="status" class="mb-6 p-4 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm flex items-center gap-2"><i class="bi bi-check-circle-fill text-emerald-600"></i><span>{{ session('success') }}</span></div>@endif
@if(session('unit_error'))<div role="alert" class="mb-6 p-4 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 text-sm flex items-center gap-2"><i class="bi bi-exclamation-triangle-fill text-rose-600"></i><span>{{ session('unit_error') }}</span></div>@endif

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-bold text-slate-900 flex items-baseline gap-2">
            Units
            <span class="text-xs font-medium text-slate-400">Manage your units</span>
        </h1>
    </div>
    <button id="add-unit" type="button" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">
        <i class="bi bi-plus-lg text-sm" aria-hidden="true"></i> Add
    </button>
</div>

<section class="bg-white rounded-2xl border border-purple-100 shadow-sm p-4 sm:p-6">
    <div class="sticky-table-host">
        <table id="units-table" class="w-full text-left" style="width:100%">
            <thead><tr><th>Name</th><th>Short name</th><th>Allow decimal <button type="button" data-unit-help="unit-decimal-help" aria-describedby="unit-decimal-help" aria-label="About decimal quantities" class="text-purple-600 hover:text-purple-700"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></button></th><th>Action</th></tr></thead>
            <tbody>@foreach($units as $unit)
                <tr>
                    <td class="font-medium text-slate-800">{{ $unit->name }}@if($unit->baseUnit)<span class="block text-xs text-slate-400 mt-1">1 {{ $unit->short_name }} = {{ rtrim(rtrim($unit->base_unit_multiplier, '0'), '.') }} {{ $unit->baseUnit->short_name }}</span>@endif</td>
                    <td class="text-slate-600">{{ $unit->short_name }}</td>
                    <td class="text-slate-600">{{ $unit->allow_decimal ? 'Yes' : 'No' }}</td>
                    <td><div class="flex items-center gap-2 whitespace-nowrap">
                        <button type="button" class="edit-unit inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-bold transition-all" data-unit="{{ json_encode($unit->only(['id', 'name', 'short_name', 'allow_decimal', 'base_unit_id', 'base_unit_multiplier'])) }}" data-url="{{ route('products.units.update', $unit) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Edit</button>
                        <form class="delete-unit" method="POST" action="{{ route('products.units.destroy', $unit) }}">@csrf @method('DELETE')<button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-bold transition-all" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button></form>
                    </div></td>
                </tr>
            @endforeach</tbody>
        </table>
    </div>
</section>

<dialog id="unit-dialog" aria-labelledby="unit-title" class="bg-white shadow-2xl text-slate-800 rounded-2xl p-0 overflow-hidden border-0">
    <div class="flex justify-between items-center px-6 py-4 bg-purple-50 border-b border-purple-100"><h2 id="unit-title" class="text-base font-bold text-slate-800">Add Unit</h2><button type="button" data-close-unit aria-label="Close unit form" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-purple-100 flex items-center justify-center transition-colors"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
    <form id="unit-form" method="POST" action="{{ old('_unit_id') ? route('products.units.update', old('_unit_id')) : route('products.units.store') }}">
        @csrf
        <input type="hidden" name="_method" value="{{ old('_unit_id') ? 'PUT' : 'POST' }}"><input type="hidden" name="_unit_id" value="{{ old('_unit_id') }}">
        <div class="p-6 space-y-4">
            @if($errors->any())<div id="unit-errors" role="alert" class="p-3 rounded-xl bg-rose-50 text-rose-700 text-sm border border-rose-100"><ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div><label for="unit-name" class="block text-xs font-bold text-slate-700 mb-1.5">Name <span class="text-rose-500">*</span></label><input id="unit-name" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="Name" class="{{ $input }}"></div>
            <div><label for="unit-short" class="block text-xs font-bold text-slate-700 mb-1.5">Short name <span class="text-rose-500">*</span></label><input id="unit-short" name="short_name" maxlength="30" required value="{{ old('short_name') }}" placeholder="Short name" class="{{ $input }}"></div>
            <div><label for="unit-decimal" class="block text-xs font-bold text-slate-700 mb-1.5">Allow decimal <span class="text-rose-500">*</span></label><select id="unit-decimal" name="allow_decimal" required class="{{ $input }}"><option value="">Please Select</option><option value="1" @selected(old('allow_decimal') === '1')>Yes</option><option value="0" @selected(old('allow_decimal') === '0')>No</option></select></div>
            <div class="relative">
                <div class="flex items-center gap-2 text-sm"><label class="inline-flex items-center gap-2 font-medium text-slate-700"><input id="unit-multiple" type="checkbox" name="is_multiple" value="1" @checked(old('is_multiple')) class="accent-purple-600 rounded">Add as multiple of other unit</label>
                    <span class="inline-flex"><button type="button" data-unit-help="unit-multiple-help" aria-label="About unit multiples" aria-describedby="unit-multiple-help" class="text-purple-600 hover:text-purple-700"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></button></span>
                </div>
            </div>
            <div id="unit-conversion" hidden class="space-y-4 p-4 rounded-xl bg-purple-50/50 border border-purple-100">
                <p class="text-xs font-semibold text-purple-800">1 of this unit equals:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label for="unit-multiplier" class="block text-xs font-bold text-slate-700 mb-1.5">Multiplier <span class="text-rose-500">*</span></label><input id="unit-multiplier" name="base_unit_multiplier" type="number" min="0.000001" max="999999999999" step="0.000001" value="{{ old('base_unit_multiplier') }}" placeholder="e.g. 12" class="{{ $input }}"></div>
                    <div><label for="unit-base" class="block text-xs font-bold text-slate-700 mb-1.5">Base unit <span class="text-rose-500">*</span></label><select id="unit-base" name="base_unit_id" class="{{ $input }}"><option value="">Please Select</option>@foreach($units->whereNull('base_unit_id') as $base)<option value="{{ $base->id }}" @selected(old('base_unit_id') == $base->id)>{{ $base->name }} ({{ $base->short_name }})</option>@endforeach</select></div>
                </div>
                @if($units->whereNull('base_unit_id')->isEmpty())<p class="text-xs text-slate-500">Create a base unit first, with the multiple option unchecked.</p>@endif
            </div>
        </div>
        <div class="flex justify-end gap-2 px-6 py-4 border-t border-purple-100 bg-slate-50/50"><button id="save-unit" type="submit" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20 transition-all">Save</button><button type="button" data-close-unit class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-bold transition-all">Close</button></div>
    </form>
    <div role="tooltip" id="unit-multiple-help" class="unit-tip" hidden>Define this unit as the multiple of other units<br><strong>Ex: 1 dozen = 12 pieces</strong></div>
</dialog>
<div role="tooltip" id="unit-decimal-help" class="unit-tip" hidden>Decimals allows you to sell the related products in fractions.</div>
@endsection
@push('scripts')
<style>
    #unit-dialog { margin:auto; padding:0; border:0; border-radius:1rem; width:calc(100% - 2rem); max-width:38rem; max-height:calc(100dvh - 2rem); }
    #unit-dialog::backdrop { background:rgb(15 23 42 / .5); backdrop-filter:blur(3px); }
    .unit-tip { position:fixed; margin:0; width:17rem; padding:1rem; background:white; color:#334155; border:1px solid #e2e8f0; border-radius:.5rem; box-shadow:0 8px 20px #0002; font-size:.8125rem; line-height:1.5; font-weight:400; text-transform:none; z-index:100; }
    #units-table_wrapper .dt-buttons { display:flex; flex-wrap:wrap; gap:.25rem; }
    #units-table_wrapper .dataTables_filter, #units-table_wrapper .dataTables_length { float:none; text-align:left; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('unit-dialog'), form = document.getElementById('unit-form');
    const multiple = document.getElementById('unit-multiple'), save = document.getElementById('save-unit');
    let previousFocus;
    let helpTrigger = null, helpTip = null;
    function hideHelp() { if (helpTip) helpTip.hidden = true; helpTrigger = helpTip = null; }
    function showHelp(trigger) {
        hideHelp();
        helpTrigger = trigger; helpTip = document.getElementById(trigger.dataset.unitHelp);
        const anchor = trigger.getBoundingClientRect();
        const owner = trigger.closest('dialog');
        if (!owner) document.body.appendChild(helpTip);
        const bounds = owner ? owner.getBoundingClientRect() : {left:0, top:0, right:innerWidth, bottom:innerHeight};
        const left = Math.max(0, bounds.left) + 12, right = Math.min(innerWidth, bounds.right) - 12;
        const top = Math.max(0, bounds.top) + 12, bottom = Math.min(innerHeight, bounds.bottom) - 12;
        helpTip.style.maxWidth = Math.max(0, right-left) + 'px';
        helpTip.hidden = false;
        const size = helpTip.getBoundingClientRect();
        helpTip.style.left = Math.max(left, Math.min(anchor.left + anchor.width/2 - size.width/2, right-size.width)) + 'px';
        const below = anchor.bottom + 8;
        helpTip.style.top = Math.max(top, Math.min(below + size.height <= bottom ? below : anchor.top-size.height-8, bottom-size.height)) + 'px';
    }
    document.addEventListener('mouseover', event => { const trigger = event.target.closest('[data-unit-help]'); if (trigger && trigger !== helpTrigger) showHelp(trigger); });
    document.addEventListener('focusin', event => { const trigger = event.target.closest('[data-unit-help]'); if (trigger) showHelp(trigger); else hideHelp(); });
    document.addEventListener('mouseout', event => { if (helpTrigger && !helpTrigger.contains(event.relatedTarget) && !helpTip.contains(event.relatedTarget)) hideHelp(); });
    document.addEventListener('click', event => { const trigger = event.target.closest('[data-unit-help]'); if (trigger) { event.stopPropagation(); showHelp(trigger); } }, true);
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && helpTip) { event.preventDefault(); event.stopPropagation(); hideHelp(); } }, true);
    window.addEventListener('resize', hideHelp);
    document.addEventListener('scroll', hideHelp, true);
    dialog.addEventListener('close', hideHelp);
    function updateConversion() {
        document.getElementById('unit-conversion').hidden = !multiple.checked;
        ['base_unit_id', 'base_unit_multiplier'].forEach(key => {form.elements[key].disabled = !multiple.checked; form.elements[key].required = multiple.checked;});
        Array.from(form.elements.base_unit_id.options).forEach(option => option.disabled = option.value !== '' && option.value === form.elements._unit_id.value);
    }
    function openUnit() { previousFocus = document.activeElement; updateConversion(); save.disabled = false; save.textContent = 'Save'; dialog.showModal(); }
    multiple.addEventListener('change', updateConversion);
    document.getElementById('add-unit').addEventListener('click', () => {
        form.reset(); form.action = @json(route('products.units.store'));
        ['_unit_id', 'name', 'short_name', 'allow_decimal', 'base_unit_id', 'base_unit_multiplier'].forEach(key => form.elements[key].value = '');
        form.elements._method.value = 'POST'; multiple.checked = false;
        document.getElementById('unit-errors')?.remove(); document.getElementById('unit-title').textContent = 'Add Unit'; openUnit();
    });
    document.getElementById('units-table').addEventListener('click', event => {
        const button = event.target.closest('.edit-unit'); if (!button) return;
        const unit = JSON.parse(button.dataset.unit);
        form.action = button.dataset.url; form.elements._method.value = 'PUT'; form.elements._unit_id.value = unit.id;
        ['name', 'short_name', 'base_unit_id', 'base_unit_multiplier'].forEach(key => form.elements[key].value = unit[key] ?? '');
        form.elements.allow_decimal.value = unit.allow_decimal ? '1' : '0'; multiple.checked = unit.base_unit_id !== null;
        document.getElementById('unit-errors')?.remove(); document.getElementById('unit-title').textContent = 'Edit Unit'; openUnit();
    });
    document.getElementById('units-table').addEventListener('submit', event => {
        if (event.target.matches('.delete-unit') && !confirm('Delete this unit? This cannot be undone.')) event.preventDefault();
    });
    document.querySelectorAll('[data-close-unit]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => previousFocus?.focus());
    form.addEventListener('submit', () => { save.disabled = true; save.textContent = 'Saving…'; });
    if (@json($errors->any())) { document.getElementById('unit-title').textContent = form.elements._unit_id.value ? 'Edit Unit' : 'Add Unit'; openUnit(); }
    if (window.jQuery && $.fn.DataTable) {
        const exportOptions = { columns:[0,1,2], format:{body: data => {const text = $('<div>').html(data).text().trim(); return /^[=+\-@\t\r]/.test(text) ? "'" + text : text;}} };
        const unitsTable = $('#units-table').DataTable({pageLength:25, order:[[0,'asc']], scrollX:false, autoWidth:false,
            columnDefs:[{targets:3,orderable:false,searchable:false}],
            dom:'<"flex flex-wrap items-center justify-between gap-4 mb-5"lBf>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"ip>',
            buttons:[{extend:'csvHtml5',text:'Export to CSV',exportOptions},{extend:'excelHtml5',text:'Export to Excel',exportOptions},{extend:'print',text:'Print',exportOptions},{extend:'colvis',text:'Column visibility',columns:[0,1,2,3]},{extend:'pdfHtml5',text:'Export to PDF',exportOptions}],
            language:{emptyTable:'No units yet. Add a unit to get started.',searchPlaceholder:'Search units…'}
        });
        StickyDataTables.install(unitsTable);
    }
});
</script>
@endpush
