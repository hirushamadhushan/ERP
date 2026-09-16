@extends('layouts.app')
@section('title','Product Serial Numbers')
@section('content')
@include('serials.common')
<h1 class="text-xl font-bold mb-6">Product Serial Numbers</h1>
<section class="serial-card no-print">
<h2>Filters</h2>
<form method="GET" class="serial-grid" data-auto-filter>
@foreach(['product'=>'products','location'=>'locations'] as $type=>$collection)
<div><label for="filter-{{ $type }}">{{ ucfirst($type) }}</label><select id="filter-{{ $type }}" name="{{ $type }}_id"><option value="">All {{ $collection }}</option>@foreach($$collection as $record)<option value="{{ $record->id }}" @selected(request($type.'_id') == $record->id)>{{ $record->name }} (#{{ $record->id }})</option>@endforeach</select></div>
@endforeach
<div><label for="status">Status</label><select name="status" id="status"><option value="">All</option>@foreach(['available','sold'] as $status)<option @selected(request('status')===$status) value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></div>
@if(request()->query())<div class="flex items-end"><a class="serial-btn serial-secondary" href="{{ route('products.serials.index') }}">Reset filters</a></div>@endif
</form>
<div class="mt-6 flex flex-wrap gap-3"><a class="serial-btn serial-secondary" target="_blank" rel="noopener" href="{{ route('products.serials.report', request()->only('product_id','location_id','status')) }}">Print Report</a><button type="button" id="open-serial-generator" class="serial-btn">Generate Serial Numbers</button><form id="serial-import-form" method="POST" enctype="multipart/form-data" action="{{ route('products.serials.import') }}">@csrf<input id="serial-file" name="file" type="file" accept=".xlsx,.csv" hidden><button type="button" id="import" class="serial-btn serial-secondary">Import Excel</button></form><a class="serial-btn serial-secondary" href="{{ route('products.serials.template') }}">Download Excel template</a></div>
</section>
<section class="serial-card">
<form method="POST" action="{{ route('products.serials.destroy') }}" onsubmit="return confirm('Delete selected available serial numbers?')">
@csrf @method('DELETE')
<button class="serial-btn no-print mb-4" style="background:#e11d48">Delete Selected (Available only)</button>
<div class="sticky-table-host"><table id="serials-table" class="serial-table"><thead><tr><th class="no-print"><input type="checkbox" id="select-all" aria-label="Select all available serial numbers"></th><th>#</th><th>Product</th><th>Location</th><th>Serial Number</th><th>Status</th><th>Sold Transaction ID</th><th class="no-print">Action</th></tr></thead>
<tbody>@forelse($records as $record)<tr>
<td class="no-print">@if($record->status==='available' && !$record->sold_transaction_id)<input class="serial-select" type="checkbox" name="ids[]" value="{{ $record->id }}" aria-label="Select {{ $record->serial_number }}">@endif</td>
<td>{{ $record->id }}</td><td>{{ $record->product->name }}</td><td>{{ $record->location->name }}</td><td>{{ $record->serial_number }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->sold_transaction_id ?? '—' }}</td>
<td class="no-print">@if($record->status==='available' && !$record->sold_transaction_id)<button type="button" class="text-rose-600" onclick="document.querySelectorAll('.serial-select').forEach(c=>c.checked=c.value==='{{ $record->id }}'); this.form.requestSubmit()">Delete</button>@else Protected @endif</td>
</tr>@empty<tr><td colspan="8" class="text-center text-slate-500">No serial numbers found.</td></tr>@endforelse</tbody></table></div>
</form>
<div class="mt-5 no-print">{{ $records->links() }}</div>
</section>
<dialog id="serial-generator-dialog" aria-labelledby="serial-generator-title">
<div class="flex items-center justify-between gap-3 p-4 bg-purple-50 border-b border-purple-100">
<h2 id="serial-generator-title" class="font-bold">Serial Numbers Generate & Print</h2>
<button type="button" id="close-serial-generator" class="serial-btn serial-secondary" aria-label="Close serial generator">Close</button>
</div>
<iframe id="serial-generator-frame" title="Serial number generator" data-src="{{ route('products.serials.create', ['embedded'=>1,'product_id'=>request('product_id')]) }}"></iframe>
</dialog>
<style>
#serial-generator-dialog{position:fixed;inset:0;margin:auto;padding:0;border:0;border-radius:16px;width:min(1000px,calc(100vw - 24px));height:min(850px,calc(100dvh - 32px));max-height:calc(100dvh - 32px);overflow:hidden;box-shadow:0 24px 70px #0004}
#serial-generator-dialog[open]{display:flex;flex-direction:column}
#serial-generator-dialog::backdrop{background:rgb(15 23 42 / .5);backdrop-filter:blur(3px)}
#serial-generator-frame{width:100%;flex:1;min-height:0;border:0;background:white}
</style>
@endsection
@push('scripts')
<script>
const serialFile=document.getElementById('serial-file');
const serialImport=document.getElementById('serial-import-form');
const importButton=document.getElementById('import');
StickyDataTables.installElement('#serials-table');
document.getElementById('import').addEventListener('click',()=>serialFile.click());
serialFile.addEventListener('change',async ()=>{
    if(!serialFile.files.length)return;
    const file=serialFile.files[0];
    if(!/\.(xlsx|csv)$/i.test(file.name)||file.size>5*1024*1024){
        alert('Choose an Excel (.xlsx) or CSV file up to 5 MB.');
        serialFile.value='';
        return;
    }
    importButton.disabled=true;
    importButton.textContent='Importing…';
    try {
        await AppErrors.request(serialImport.action,{method:'POST',body:new FormData(serialImport)});
        location.reload();
    } catch(error) {
        AppErrors.show(error.message);
    } finally {
        importButton.disabled=false;
        importButton.textContent='Import Excel';
        serialFile.value='';
    }
});
const generatorDialog=document.getElementById('serial-generator-dialog');
const generatorFrame=document.getElementById('serial-generator-frame');
let generatorSaved=false;
document.getElementById('open-serial-generator').addEventListener('click',()=>{
    if(!generatorFrame.getAttribute('src')) generatorFrame.src=generatorFrame.dataset.src;
    generatorDialog.showModal();
});
@if(request()->boolean('generate'))
document.getElementById('open-serial-generator').click();
@endif
document.getElementById('close-serial-generator').addEventListener('click',()=>generatorDialog.close());
generatorDialog.addEventListener('close',()=>{if(generatorSaved) location.reload();});
window.addEventListener('message',event=>{
    if(event.origin!==location.origin || event.source!==generatorFrame.contentWindow) return;
    if(event.data?.type==='serial-generator-saved') generatorSaved=true;
    if(event.data?.type==='close-serial-generator') generatorDialog.close();
});
</script>
@endpush
@push('scripts')<script>document.getElementById('select-all').addEventListener('change',e=>document.querySelectorAll('.serial-select').forEach(c=>c.checked=e.target.checked));</script>@endpush
