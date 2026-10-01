@extends('layouts.app')
@section('title','Load / Unload Vehicle')
@section('content')
@include('serials.common')
<h1 class="text-xl font-bold mb-2">Load / Unload Vehicle</h1>
<p class="text-sm text-slate-500 mb-5">Choose the vehicle and warehouse, then select stock from the source. Quantities use the product's base unit.</p>
<form method="POST" id="delivery-transfer" action="{{ route('delivery.transfers.store') }}">
@csrf
<input type="hidden" name="request_key" value="{{ old('request_key',(string) Illuminate\Support\Str::uuid()) }}">
<section class="serial-card"><div class="serial-grid">
<div><label for="direction">Operation *</label><select id="direction" name="direction" required><option value="loading" @selected(old('direction',request('direction'))==='loading')>Loading: warehouse → vehicle</option><option value="unloading" @selected(old('direction',request('direction'))==='unloading')>Unloading: vehicle → warehouse</option></select></div>
<div><label for="vehicle_id">Vehicle *</label><select id="vehicle_id" name="vehicle_id" required><option value="">Select vehicle</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('vehicle_id',request('vehicle_id'))==$vehicle->id)>{{ $vehicle->number }} — {{ $vehicle->name }} / {{ $vehicle->drivers->first()?->name ?? 'No driver' }}{{ !$vehicle->is_active ? ' (Inactive)' : '' }}</option>@endforeach</select></div>
<div><label for="warehouse_id">Warehouse / Branch *</label><select id="warehouse_id" name="warehouse_id" required><option value="">Select warehouse</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id')==$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
<div><label for="reference">Reference</label><input id="reference" name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="Delivery reference"></div>
</div></section>
<section class="serial-card"><h2>Select source stock</h2><div class="serial-grid"><div><label for="stock-search">Search product / SKU</label><input id="stock-search" type="search" placeholder="Search available source stock" maxlength="100"></div><div><label for="stock-option">Available product, lot or serial</label><select id="stock-option"><option value="">Select vehicle and warehouse first</option></select></div><div class="flex items-end"><button type="button" id="add-stock" class="serial-btn">+ Add item</button></div></div>
<p id="stock-message" role="status" class="text-sm text-slate-500 my-4">Results show up to 25 matching products and 100 lots/serials per variation. Refine your search as needed.</p>
<div class="overflow-x-auto"><table class="serial-table"><thead><tr><th>Product / Variation / Lot / Serial</th><th>Quantity *</th><th></th></tr></thead><tbody id="transfer-lines"></tbody></table></div>
<div class="mt-5"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="2000" class="w-full border border-slate-300 rounded-xl p-3">{{ old('notes') }}</textarea></div>
<div class="mt-5"><button id="save-transfer" class="serial-btn">Confirm transfer</button> <a class="serial-btn serial-secondary" href="{{ route('delivery.store') }}">Cancel</a></div>
</section></form>
<script type="application/json" id="previous-transfer-lines">@json(old('lines',[]))</script>
@endsection
@push('scripts')
<script>
AppPage.ready(() => {
    const form = document.getElementById('delivery-transfer');
    if (!form) return;
    const sourceFields = ['direction','vehicle_id','warehouse_id'].map(id => document.getElementById(id));
    const search = document.getElementById('stock-search'), options = document.getElementById('stock-option');
    const body = document.getElementById('transfer-lines'), message = document.getElementById('stock-message');
    let entries = [], counter = 0, controller, timer, sourceValues = sourceFields.map(input => input.value);
    function addRow(entry) {
        const row = document.createElement('tr'), name = document.createElement('td'), quantityCell = document.createElement('td'), action = document.createElement('td');
        const prefix = `lines[${counter++}]`;
        name.textContent = entry.label || `Product #${entry.product_id}`;
        for (const key of ['product_id','variant_id','lot_id','label']) {
            if (entry[key] == null || entry[key] === '') continue;
            const input = document.createElement('input'); input.type = 'hidden'; input.name = `${prefix}[${key}]`; input.value = entry[key]; name.append(input);
        }
        const serialIds = entry.serial_ids || (entry.serial_id ? [entry.serial_id] : []);
        for (const id of serialIds) { const input = document.createElement('input'); input.type='hidden'; input.name=`${prefix}[serial_ids][]`; input.value=id; name.append(input); }
        const input = document.createElement('input'); input.name = `${prefix}[quantity]`; input.type='number'; input.required=true; input.min='0.0001'; input.max='999999999'; input.step='0.0001'; input.value=entry.quantity || (serialIds.length ? serialIds.length : '');
        input.setAttribute('aria-label', 'Transfer quantity'); if(serialIds.length) input.readOnly=true;
        quantityCell.append(input); const remove=document.createElement('button');remove.type='button';remove.className='serial-btn serial-secondary';remove.textContent='Remove';remove.addEventListener('click',()=>row.remove()); action.append(remove);
        row.append(name,quantityCell,action);body.append(row);
    }
    for (const entry of Object.values(JSON.parse(document.getElementById('previous-transfer-lines').textContent))) addRow(entry);
    async function load() {
        controller?.abort(); controller = new AbortController(); entries=[]; options.replaceChildren(new Option('Choose available stock',''));
        if(sourceFields.some(input=>!input.value)) return;
        const params=new URLSearchParams(sourceFields.map(input=>[input.name,input.value]));params.set('q',search.value);
        message.textContent='Loading available stock…';
        try {
            const response=await fetch(@json(route('delivery.options'))+'?'+params,{headers:{Accept:'application/json'},signal:controller.signal});
            const data=await response.json();if(!response.ok) throw new Error(data.message || 'Unable to load stock.');
            entries=data; entries.forEach((entry,index)=>options.add(new Option(`${entry.label} — Available: ${entry.available} ${entry.unit || ''}`,index)));
            message.textContent=entries.length?'Select an item and enter the quantity. Refine the search if your item is not listed.':'No available stock matches this source and search.';
        } catch(error) {if(error.name!=='AbortError') message.textContent=error.message;}
    }
    sourceFields.forEach(input=>input.addEventListener('change',()=>{
        if(body.children.length && !confirm('Changing the source clears selected items. Continue?')) {sourceFields.forEach((field,index)=>field.value=sourceValues[index]);return;}
        sourceValues=sourceFields.map(field=>field.value);body.replaceChildren();load();
    }));
    search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(load,300);});
    document.getElementById('add-stock').addEventListener('click',()=>{if(options.value==='')return;const entry=entries[Number(options.value)];if(entry)addRow(entry);});
    form.addEventListener('submit',event=>{if(!body.children.length){event.preventDefault();message.textContent='Add at least one stock item.';return;}document.getElementById('save-transfer').disabled=true;});
    AppPage.signal.addEventListener('abort',()=>{controller?.abort();clearTimeout(timer);},{once:true});
    load();
});
</script>
@endpush
