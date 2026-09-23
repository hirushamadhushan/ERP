@extends('layouts.app')
@section('title','Invoice Settings')
@section('content')
<div class="mb-5 flex flex-wrap items-baseline gap-3"><h1 class="text-xl font-bold text-slate-900">Invoice Settings</h1><span class="text-sm text-slate-500">Manage invoice numbering and layouts</span></div>
@foreach(['status'=>'emerald','error'=>'rose'] as $key=>$color)@if(session($key))<div id="invoice-{{ $key }}-alert" class="mb-4 rounded-xl border border-{{ $color }}-200 bg-{{ $color }}-50 p-4 text-sm font-semibold text-{{ $color }}-800">{{ session($key) }}</div>@endif @endforeach
<section class="overflow-hidden rounded-2xl border border-purple-100 bg-white shadow-sm">
    <div class="flex border-b border-purple-100 px-4">
        <button class="invoice-tab border-b-2 {{ request('tab')==='layouts'?'border-transparent text-slate-500':'border-purple-600 text-purple-700' }} px-4 py-4 text-sm font-bold" data-panel="schemes">Invoice Schemes</button>
        <button class="invoice-tab border-b-2 {{ request('tab')==='layouts'?'border-purple-600 text-purple-700':'border-transparent text-slate-500' }} px-4 py-4 text-sm font-bold" data-panel="layouts">Invoice Layouts</button>
    </div>
    <div id="schemes-panel" class="{{ request('tab')==='layouts'?'hidden ':'' }}p-4 sm:p-6">
        <div class="mb-5 flex items-center justify-between"><h2 class="font-bold text-slate-900">All your invoice schemes</h2><button id="add-scheme" class="rounded-xl bg-purple-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-purple-500/20 hover:bg-purple-700"><i class="bi bi-plus-lg"></i> Add</button></div>
        <table id="schemes-table" class="w-full text-left">
            <thead><tr>
                <th>Name <span class="help-tip" data-tip="Give a short meaningful name to the Invoice Scheme."><i class="bi bi-info-circle-fill"></i></span></th>
                <th>Prefix <span class="help-tip" data-tip="Prefix for an Invoice Scheme. A Prefix can be a custom text or current year. Ex: #XXXX0001, #2018-0002."><i class="bi bi-info-circle-fill"></i></span></th>
                <th>Start from <span class="help-tip" data-tip="Start number for invoice numbering. You can make it 1 or any other number from which numbering will start."><i class="bi bi-info-circle-fill"></i></span></th>
                <th>Invoice Count <span class="help-tip" data-tip="Total number of invoices generated with this Invoice Scheme."><i class="bi bi-info-circle-fill"></i></span></th>
                <th>Number of digits <span class="help-tip" data-tip="Length of the invoice number excluding its prefix."><i class="bi bi-info-circle-fill"></i></span></th>
                <th>Action</th>
            </tr></thead>
            <tbody>@foreach($schemes as $scheme)<tr>
                <td class="font-semibold">{{ $scheme->name }} @if($scheme->is_default)<span class="ml-2 rounded-full bg-purple-100 px-2 py-1 text-[10px] font-bold text-purple-700">Default</span>@endif</td>
                <td>{{ $scheme->prefix ?: '—' }}</td><td>{{ $scheme->start_number }}</td><td>{{ $scheme->invoice_count }}</td><td>{{ $scheme->number_of_digits }}</td>
                <td><div class="flex gap-2"><button class="edit-scheme rounded-lg bg-purple-50 px-3 py-1.5 text-xs font-bold text-purple-700" data-scheme='@json($scheme)' data-url="{{ route('business.invoice-settings.update',$scheme) }}"><i class="bi bi-pencil-square"></i> Edit</button>@unless($scheme->is_default)<form method="POST" action="{{ route('business.invoice-settings.destroy',$scheme) }}" onsubmit="return confirm('Delete this invoice scheme?')">@csrf @method('DELETE')<button class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700"><i class="bi bi-trash"></i> Delete</button></form>@endunless</div></td>
            </tr>@endforeach</tbody>
        </table>
    </div>
    <div id="layouts-panel" class="{{ request('tab')==='layouts'?'':'hidden ' }}p-4 sm:p-6">
        <div class="mb-5 flex items-center justify-between"><h2 class="font-bold text-slate-900">All your invoice layouts</h2><a href="{{ route('business.invoice-settings.layouts.create') }}" class="rounded-xl bg-purple-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-purple-500/20 hover:bg-purple-700"><i class="bi bi-plus-lg"></i> Add</a></div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach($layouts as $layout)@php($used=$layout->posLocations->merge($layout->saleLocations)->unique('id')->pluck('name'))<a href="{{ route('business.invoice-settings.layouts.edit',$layout) }}" class="group rounded-2xl border border-purple-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-purple-300 hover:shadow-md"><div class="relative mx-auto mb-3 flex h-16 w-14 items-center justify-center rounded-lg bg-purple-100 text-3xl text-purple-600"><i class="bi bi-file-earmark-text-fill"></i>@if($layout->is_default)<span class="absolute -right-6 top-5 rounded-full bg-purple-600 px-2 py-0.5 text-[9px] font-bold text-white">Default</span>@endif</div><h3 class="text-center text-sm font-bold text-purple-700 group-hover:text-purple-900">{{ $layout->name }}</h3><p class="mt-3 text-xs font-bold text-slate-700">Used in locations:</p><p class="mt-1 text-xs text-slate-500">{{ $used->implode(', ') ?: 'Not assigned' }}</p></a>@endforeach</div>
    </div>
</section>

<dialog id="scheme-dialog" class="w-[calc(100%-2rem)] max-w-2xl rounded-2xl border-0 p-0 shadow-2xl">
<form id="scheme-form" method="POST" action="{{ route('business.invoice-settings.store') }}">@csrf<input name="_method" type="hidden" value="POST">
    <div class="flex items-center justify-between border-b border-purple-100 bg-purple-50 px-5 py-4"><h2 id="scheme-title" class="font-bold text-purple-900">Add new invoice scheme</h2><button type="button" data-close aria-label="Close" class="rounded-lg p-1 text-slate-400 hover:bg-purple-100"><i class="bi bi-x-lg"></i></button></div>
    <div class="p-5">
        @if($errors->any())<div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_1fr_1.1fr]">
            <button type="button" class="format-choice relative rounded-xl border-2 border-transparent bg-slate-100 p-4 text-left text-purple-900" data-format="number"><span class="block text-xs font-bold text-slate-500">FORMAT 1</span><span class="text-lg font-extrabold">XXXX</span><i class="selected-icon bi bi-check-circle-fill absolute bottom-3 right-3 hidden text-purple-600"></i></button>
            <button type="button" class="format-choice relative rounded-xl border-2 border-transparent bg-slate-100 p-4 text-left text-purple-900" data-format="year_number"><span class="block text-xs font-bold text-slate-500">FORMAT 2</span><span class="text-lg font-extrabold">{{ now()->year }}-XXXX</span><i class="selected-icon bi bi-check-circle-fill absolute bottom-3 right-3 hidden text-purple-600"></i></button>
            <div class="rounded-xl border border-purple-100 p-4"><span class="block text-xs font-bold text-slate-700">Preview:</span><strong id="scheme-preview" class="mt-2 block text-purple-700">Not selected</strong></div>
        </div>
        <input id="scheme-format" name="format" type="hidden" value="{{ old('format') }}">
        <label class="mt-5 block text-xs font-bold text-slate-700">Name:<span class="text-rose-500">*</span><input name="name" required maxlength="100" placeholder="Name" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-100"></label>
        <div id="scheme-fields" class="mt-5 hidden grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="text-xs font-bold text-slate-700">Prefix:<input name="prefix" maxlength="30" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-100"></label>
            <label class="text-xs font-bold text-slate-700">Start from:<input name="start_number" type="number" min="0" value="0" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-100"></label>
            <label class="text-xs font-bold text-slate-700">Number of digits:<select name="number_of_digits" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-100">@for($i=1;$i<=12;$i++)<option @selected($i===4)>{{ $i }}</option>@endfor</select></label>
            <label class="flex items-center gap-2 self-end pb-3 text-xs font-semibold text-slate-700"><input type="hidden" name="is_default" value="0"><input name="is_default" type="checkbox" value="1" class="accent-purple-600"> Set as default</label>
        </div>
    </div>
    <div class="flex justify-end gap-2 border-t border-purple-100 px-5 py-4"><button type="submit" class="rounded-xl bg-purple-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-purple-700">Save</button><button type="button" data-close class="rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-600">Close</button></div>
</form></dialog>
@endsection
@push('scripts')
<style>#scheme-dialog{margin:auto}#scheme-dialog::backdrop{background:rgb(15 23 42/.55);backdrop-filter:blur(3px)}.format-choice.selected{border-color:#7c3aed;background:#f5f3ff}.format-choice.selected .selected-icon{display:block}.help-tip{position:relative;display:inline-flex;cursor:help;color:#7c3aed}.help-tip::after{content:attr(data-tip);position:absolute;z-index:30;top:calc(100% + 10px);left:50%;width:260px;transform:translateX(-50%);border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.7rem .8rem;color:#334155;font-size:.8rem;font-weight:400;line-height:1.4;box-shadow:0 5px 14px rgb(15 23 42/.2);opacity:0;pointer-events:none;transition:opacity .15s}.help-tip:hover::after,.help-tip:focus::after{opacity:1}</style>
<script>
 document.addEventListener('DOMContentLoaded',()=>{const alert=document.getElementById('invoice-status-alert');if(alert)setTimeout(()=>{alert.style.transition='opacity .35s';alert.style.opacity='0';setTimeout(()=>alert.remove(),400)},4000);});
document.addEventListener('DOMContentLoaded',()=>{
 const dialog=document.getElementById('scheme-dialog'),form=document.getElementById('scheme-form'),fields=document.getElementById('scheme-fields'),format=document.getElementById('scheme-format'),preview=document.getElementById('scheme-preview');
 const updatePreview=()=>{if(!format.value){preview.textContent='Not selected';return}const prefix=form.elements.prefix.value||(format.value==='year_number'?@json((string) now()->year.'-'):'#'),digits=Number(form.elements.number_of_digits.value||4),start=String(form.elements.start_number.value||0).padStart(digits,'0');preview.textContent=prefix+start};
 const choose=value=>{format.value=value;fields.classList.remove('hidden');fields.classList.add('grid');if(!form.elements.prefix.value||form.elements.prefix.value==='#')form.elements.prefix.value=value==='year_number'?@json((string) now()->year.'-'):'#';if(!form.elements.start_number.value)form.elements.start_number.value='0';if(!form.elements.number_of_digits.value)form.elements.number_of_digits.value='4';document.querySelectorAll('.format-choice').forEach(x=>x.classList.toggle('selected',x.dataset.format===value));updatePreview()};
 const open=(scheme=null,url=null)=>{form.reset();form.action=url||@json(route('business.invoice-settings.store'));form.elements._method.value=scheme?'PUT':'POST';document.getElementById('scheme-title').textContent=scheme?'Edit invoice scheme':'Add new invoice scheme';format.value='';fields.classList.add('hidden');fields.classList.remove('grid');document.querySelectorAll('.format-choice').forEach(x=>x.classList.remove('selected'));preview.textContent='Not selected';if(scheme){['name','prefix','start_number','number_of_digits'].forEach(k=>form.elements[k].value=scheme[k]??'');form.elements.is_default.checked=!!scheme.is_default;choose(scheme.format)}dialog.showModal()};
 document.getElementById('add-scheme').onclick=()=>open();document.querySelectorAll('.edit-scheme').forEach(b=>b.onclick=()=>open(JSON.parse(b.dataset.scheme),b.dataset.url));document.querySelectorAll('.format-choice').forEach(b=>b.onclick=()=>choose(b.dataset.format));document.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>dialog.close());['prefix','start_number','number_of_digits'].forEach(k=>form.elements[k].addEventListener('input',updatePreview));
 document.querySelectorAll('.invoice-tab').forEach(b=>b.onclick=()=>{document.querySelectorAll('.invoice-tab').forEach(x=>{x.classList.toggle('border-purple-600',x===b);x.classList.toggle('text-purple-700',x===b);x.classList.toggle('border-transparent',x!==b)});document.getElementById('schemes-panel').classList.toggle('hidden',b.dataset.panel!=='schemes');document.getElementById('layouts-panel').classList.toggle('hidden',b.dataset.panel!=='layouts')});
 if(window.jQuery?.fn?.DataTable) $('#schemes-table').DataTable({pageLength:25,order:[[0,'asc']],columnDefs:[{targets:5,orderable:false}],language:{emptyTable:'No invoice schemes found.',searchPlaceholder:'Search schemes…'}});
 @if($errors->any()) open(); @endif
});
</script>@endpush
