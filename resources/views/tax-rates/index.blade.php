@extends('layouts.app')
@section('title','Tax Rates')
@section('subtitle','Manage tax rates and groups')
@section('content')
@php $input='w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20'; @endphp
<div id="tax-toast" class="fixed right-5 top-3 z-[80] hidden w-[min(360px,calc(100%-2rem))] rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-2xl" role="status"></div>
<div class="mb-5 flex flex-wrap items-baseline gap-3"><h1 class="text-xl font-bold text-slate-900">Tax Rates</h1><span class="text-sm text-slate-500">Manage your tax rates</span></div>

<div class="space-y-6">
@foreach([['single','All your tax rates',$taxRates],['group','Tax groups (Combination of multiple taxes)',$taxGroups]] as [$type,$heading,$records])
<section class="rounded-2xl border border-purple-100 bg-white shadow-sm">
 <div class="flex items-center justify-between border-b border-purple-100 px-5 py-4"><h2 class="font-bold text-slate-800">{{ $heading }}</h2><button type="button" data-add="{{ $type }}" class="rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-md shadow-purple-500/20 hover:bg-purple-700"><i class="bi bi-plus-lg"></i> Add</button></div>
 <div class="overflow-x-auto p-5"><table data-async-table id="tax-{{ $type }}-table" class="tax-table w-full min-w-[620px] text-left text-sm"><thead><tr class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><th class="p-3">Name</th><th class="p-3">Tax Rate %</th>@if($type==='group')<th class="p-3">Sub taxes</th>@endif<th class="p-3">Action</th></tr></thead><tbody>
 @forelse($records as $record)<tr class="border-b border-slate-100">
  <td class="p-3 font-semibold">{{ $record->name }} @if(!$record->is_tax_group && $record->for_tax_group)<span class="text-xs font-normal text-purple-600">(For tax group only)</span>@endif</td>
  <td class="p-3 tabular-nums">{{ number_format((float)$record->amount,3) }}%</td>
  @if($type==='group')<td class="p-3 text-slate-500">{{ $record->subTaxes->pluck('name')->join(' + ') }}</td>@endif
  @php
      $recordPayload = [
          'id' => $record->id,
          'name' => $record->name,
          'amount' => $record->amount,
          'for_tax_group' => $record->for_tax_group,
          'tax_rate_ids' => $record->is_tax_group ? $record->subTaxes->pluck('id')->values() : [],
      ];
  @endphp
  <td class="p-3"><div class="flex gap-2"><button type="button" class="tax-edit rounded-lg bg-purple-50 px-3 py-1.5 text-xs font-bold text-purple-700" data-type="{{ $type }}" data-record='@json($recordPayload)' data-url="{{ $type==='single'?route('business.tax-rates.update',$record):route('business.tax-rates.groups.update',$record) }}"><i class="bi bi-pencil-square"></i> Edit</button>
  <form method="POST" class="tax-delete" action="{{ $type==='single'?route('business.tax-rates.destroy',$record):route('business.tax-rates.groups.destroy',$record) }}">@csrf @method('DELETE')<button class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700"><i class="bi bi-trash"></i> Delete</button></form></div></td>
 </tr>@empty<tr><td colspan="{{ $type==='group'?4:3 }}" class="p-8 text-center text-slate-400">No {{ $type==='group'?'tax groups':'tax rates' }} added yet.</td></tr>@endforelse
 </tbody></table></div>
</section>
@endforeach
</div>

<dialog id="tax-dialog" class="m-auto w-[calc(100%-2rem)] max-w-xl overflow-hidden rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-900/55 backdrop:backdrop-blur-sm">
 <div class="flex items-center justify-between border-b border-purple-100 bg-purple-50 px-6 py-4"><h2 id="tax-dialog-title" class="font-bold">Add Tax Rate</h2><button type="button" data-close class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-purple-100"><i class="bi bi-x-lg"></i></button></div>
 <form data-async-form id="tax-form" method="POST">@csrf<div id="tax-method"></div><div class="space-y-4 p-6">
  <div><label class="mb-1.5 block text-xs font-bold" for="tax-name">Name *</label><input class="{{ $input }}" id="tax-name" name="name" maxlength="100" required></div>
  <div id="single-fields"><label class="mb-1.5 block text-xs font-bold" for="tax-amount">Tax Rate % *</label><input class="{{ $input }}" id="tax-amount" name="amount" type="number" min="0" max="100" step="0.001"><input type="hidden" name="for_tax_group" value="0"><label class="mt-4 flex items-center gap-2 text-sm"><input id="tax-group-only" name="for_tax_group" type="checkbox" value="1" class="accent-purple-600"> For tax group only <span title="Hidden from product forms; available while building groups." class="grid h-4 w-4 cursor-help place-items-center rounded-full bg-purple-100 text-[10px] font-bold text-purple-700">i</span></label></div>
  <div data-async-region id="group-fields" hidden><p class="mb-2 text-xs font-bold text-slate-700">Sub taxes *</p><div class="max-h-60 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3">@forelse($taxRates as $tax)<label class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 hover:bg-purple-50"><span><input type="checkbox" name="tax_rate_ids[]" value="{{ $tax->id }}" class="tax-component mr-2 accent-purple-600">{{ $tax->name }}</span><strong class="text-xs text-purple-700">{{ number_format((float)$tax->amount,3) }}%</strong></label>@empty<p class="text-sm text-slate-500">Create a single tax rate first.</p>@endforelse</div><p class="mt-3 text-sm text-slate-500">Combined rate: <strong id="group-total" class="text-purple-700">0.000%</strong></p></div>
 </div><div class="flex justify-end gap-2 border-t border-purple-100 bg-slate-50/60 px-6 py-4"><button type="button" data-close class="rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-600">Close</button><button class="rounded-xl bg-purple-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-purple-700">Save</button></div></form>
</dialog>

<dialog id="confirm-dialog" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl border-0 p-7 text-center shadow-2xl backdrop:bg-slate-900/55"><div class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full border-4 border-orange-200 text-3xl text-orange-400">!</div><h2 class="text-xl font-bold">Are you sure?</h2><p class="mt-2 text-sm text-slate-500">Delete this tax record? This action cannot be undone.</p><div class="mt-6 flex justify-center gap-3"><button type="button" data-cancel-delete class="rounded-xl bg-slate-100 px-5 py-2.5 text-xs font-bold">Cancel</button><button type="button" data-confirm-delete class="rounded-xl bg-rose-500 px-5 py-2.5 text-xs font-bold text-white">Delete</button></div></dialog>
@endsection
@push('scripts')
<script>
AppPage.ready(()=>{
 const dialog=document.getElementById('tax-dialog'),form=document.getElementById('tax-form'),single=document.getElementById('single-fields'),group=document.getElementById('group-fields');
 const toast=(message,ok=false)=>{const el=document.getElementById('tax-toast');el.textContent=message;el.className=el.className.replace(/bg-(emerald|rose)-600/g,'').replace(' hidden','')+' '+(ok?'bg-emerald-600':'bg-rose-600');clearTimeout(window.taxToastTimer);window.taxToastTimer=setTimeout(()=>el.classList.add('hidden'),4000)};
 @if(session('success')) toast(@json(session('success')),true); @endif
 @if($errors->any()) toast(@json($errors->first())); @endif
 const urls={single:@json(route('business.tax-rates.store')),group:@json(route('business.tax-rates.groups.store'))};
 const total=()=>{let sum=0;document.querySelectorAll('.tax-component:checked').forEach(c=>sum+=Number(c.closest('label').querySelector('strong').textContent.replace('%','')));document.getElementById('group-total').textContent=sum.toFixed(3)+'%'};
 const open=(type,record=null,url=null)=>{form.reset();form.action=url||urls[type];document.getElementById('tax-method').innerHTML=record?'@method("PUT")':'';document.getElementById('tax-dialog-title').textContent=(record?'Edit ':'Add ')+(type==='group'?'Tax Group':'Tax Rate');single.hidden=type==='group';group.hidden=type!=='group';single.querySelectorAll('input').forEach(i=>i.disabled=type==='group');group.querySelectorAll('input').forEach(i=>i.disabled=type!=='group');if(record){form.elements.name.value=record.name;if(type==='single'){form.elements.amount.value=record.amount;document.getElementById('tax-group-only').checked=!!record.for_tax_group}else{record.tax_rate_ids.forEach(id=>{const c=form.querySelector(`.tax-component[value="${id}"]`);if(c)c.checked=true})}}total();dialog.showModal()};
 document.querySelectorAll('[data-add]').forEach(b=>b.onclick=()=>open(b.dataset.add));document.addEventListener('click',e=>{const b=e.target.closest('.tax-edit');if(b)open(b.dataset.type,JSON.parse(b.dataset.record),b.dataset.url)});document.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>dialog.close());group.addEventListener('change',total);
 const confirm=document.getElementById('confirm-dialog');let pending;document.addEventListener('submit',e=>{if(!e.target.matches('.tax-delete'))return;e.preventDefault();pending=e.target;confirm.showModal()});document.querySelector('[data-cancel-delete]').onclick=()=>confirm.close();document.querySelector('[data-confirm-delete]').onclick=()=>{confirm.close();if(pending)AsyncForms.submit(pending)};
 document.querySelectorAll('.tax-table').forEach(t=>{if(window.jQuery&&$.fn.DataTable)$(t).DataTable({pageLength:25,order:[[0,'asc']],dom:'lBfrtip',buttons:['csv','excel','print','colvis','pdf']})});
});
</script>
@endpush
