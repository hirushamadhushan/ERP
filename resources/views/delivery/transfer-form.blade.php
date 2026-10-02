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
<section class="serial-card"><h2>Select source stock</h2><div class="stock-picker-row"><div class="stock-combobox"><label for="stock-search">Product / SKU / Variation / Lot / Serial</label><div class="stock-combobox-control"><input id="stock-search" type="search" role="combobox" aria-autocomplete="list" aria-controls="stock-results" aria-expanded="false" autocomplete="off" placeholder="Search or choose available source stock" maxlength="100"><button type="button" id="stock-toggle" aria-label="Show available stock" tabindex="-1">⌄</button></div><input id="stock-option" type="hidden" value=""><div id="stock-results" class="stock-results" role="listbox" hidden></div></div><div class="stock-available-wrap"><span>Available</span><strong id="stock-available">—</strong></div><div><label for="stock-quantity">Quantity</label><input id="stock-quantity" type="number" min="0.0001" max="999999999" step="0.0001" placeholder="0" disabled></div><div class="flex items-end"><button type="button" id="add-stock" class="serial-btn" disabled>+ Add item</button></div></div>
<p id="stock-message" role="status" class="text-sm text-slate-500 my-4">Results show up to 25 matching products and 100 lots/serials per variation. Refine your search as needed.</p>
<div class="overflow-x-auto"><table class="serial-table"><thead><tr><th>Product / Variation / Lot / Serial</th><th>Quantity *</th><th></th></tr></thead><tbody id="transfer-lines"></tbody></table></div>
<div class="mt-5"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="2000" class="w-full border border-slate-300 rounded-xl p-3">{{ old('notes') }}</textarea></div>
<div class="mt-5"><button id="save-transfer" class="serial-btn">Confirm transfer</button> <a class="serial-btn serial-secondary" href="{{ route('delivery.store') }}">Cancel</a></div>
</section></form>
<script type="application/json" id="previous-transfer-lines">@json(old('lines',[]))</script>
@endsection
@push('scripts')
<style>
.stock-picker-row{display:grid;grid-template-columns:minmax(280px,1fr) 105px 130px auto;gap:12px;align-items:end}.stock-combobox{position:relative}.stock-combobox-control{position:relative}.stock-combobox-control input{padding-right:42px}.stock-combobox-control button{position:absolute;right:1px;top:1px;bottom:1px;width:40px;border-radius:0 11px 11px 0;color:#64748b;font-size:20px}.stock-combobox-control button:hover{background:#f5f3ff;color:#7e22ce}.stock-available-wrap{min-height:42px;padding:5px 9px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}.stock-available-wrap span{display:block;font-size:10px;color:#64748b}.stock-available-wrap strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#6d28d9}.stock-results{position:absolute;z-index:60;top:calc(100% + 6px);left:0;right:0;max-height:300px;overflow-y:auto;border:1px solid #d8b4fe;border-radius:12px;background:#fff;box-shadow:0 16px 35px rgb(15 23 42 / .16)}.stock-result{display:flex;width:100%;justify-content:space-between;gap:16px;padding:10px 12px;text-align:left;border-bottom:1px solid #f1f5f9;color:#334155}.stock-result:last-child{border-bottom:0}.stock-result:hover,.stock-result.active{background:#f5f3ff;color:#7e22ce}.stock-result-name{min-width:0}.stock-result-stock{flex:none;color:#64748b;font-size:12px}.stock-result-empty{padding:14px;color:#64748b;font-size:13px}@media(max-width:900px){.stock-picker-row{grid-template-columns:minmax(0,1fr) 100px 120px}.stock-picker-row>div:last-child{grid-column:1/-1}.stock-picker-row .serial-btn{width:100%}}@media(max-width:640px){.stock-picker-row{grid-template-columns:1fr}.stock-picker-row>div:last-child{grid-column:auto}}
</style>
<script>
AppPage.ready(() => {
    const form = document.getElementById('delivery-transfer');
    if (!form) return;
    const sourceFields = ['direction','vehicle_id','warehouse_id'].map(id => document.getElementById(id));
    const search = document.getElementById('stock-search'), selected = document.getElementById('stock-option');
    const results = document.getElementById('stock-results'), toggle = document.getElementById('stock-toggle');
    const available = document.getElementById('stock-available'), quantity = document.getElementById('stock-quantity'), addButton = document.getElementById('add-stock');
    const body = document.getElementById('transfer-lines'), message = document.getElementById('stock-message');
    let entries = [], counter = 0, controller, timer, activeIndex = -1, sourceValues = sourceFields.map(input => input.value);
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
    const closeResults=()=>{results.hidden=true;search.setAttribute('aria-expanded','false');activeIndex=-1;};
    const openResults=()=>{if(sourceFields.every(input=>input.value)){results.hidden=false;search.setAttribute('aria-expanded','true');}};
    function renderResults() {
        selected.value='';activeIndex=-1;results.replaceChildren();
        if(!entries.length){const empty=document.createElement('div');empty.className='stock-result-empty';empty.textContent='No available stock matches your search.';results.append(empty);openResults();return;}
        entries.forEach((entry,index)=>{const option=document.createElement('button');option.type='button';option.className='stock-result';option.setAttribute('role','option');option.dataset.index=index;const name=document.createElement('span');name.className='stock-result-name';name.textContent=entry.label;const stock=document.createElement('span');stock.className='stock-result-stock';stock.textContent=`Available: ${entry.available} ${entry.unit||''}`;option.append(name,stock);results.append(option);});
        openResults();
    }
    function resetSelection(){selected.value='';available.textContent='—';quantity.value='';quantity.disabled=true;quantity.readOnly=false;quantity.step='0.0001';quantity.max='999999999';addButton.disabled=true;}
    function choose(index){const entry=entries[index];if(!entry)return;selected.value=String(index);search.value=entry.label;available.textContent=`${entry.available} ${entry.unit||''}`;quantity.disabled=false;quantity.readOnly=Boolean(entry.serial_id);quantity.step=entry.decimal?'0.0001':'1';quantity.max=String(entry.available);quantity.value=entry.serial_id?'1':'';addButton.disabled=false;message.textContent=`Selected: ${entry.label}. Enter the quantity and click Add item.`;closeResults();if(!entry.serial_id)quantity.focus();}
    async function load() {
        controller?.abort();controller=new AbortController();entries=[];resetSelection();
        if(sourceFields.some(input=>!input.value)){results.replaceChildren();closeResults();message.textContent='Select operation, vehicle and warehouse first.';return;}
        const params=new URLSearchParams(sourceFields.map(input=>[input.name,input.value]));params.set('q',search.value);
        message.textContent='Loading available stock…';
        try {
            const response=await fetch(@json(route('delivery.options'))+'?'+params,{headers:{Accept:'application/json'},signal:controller.signal});
            const data=await response.json();if(!response.ok) throw new Error(data.message || 'Unable to load stock.');
            entries=data;renderResults();
            message.textContent=entries.length?`${entries.length} available item${entries.length===1?'':'s'} found. Select one from the list.`:'No available stock matches this source and search.';
        } catch(error) {if(error.name!=='AbortError') message.textContent=error.message;}
    }
    sourceFields.forEach(input=>input.addEventListener('change',()=>{
        if(body.children.length && !confirm('Changing the source clears selected items. Continue?')) {sourceFields.forEach((field,index)=>field.value=sourceValues[index]);return;}
        sourceValues=sourceFields.map(field=>field.value);body.replaceChildren();search.value='';resetSelection();closeResults();load();
    }));
    search.addEventListener('focus',()=>{if(entries.length)openResults();else load();});
    search.addEventListener('input',()=>{resetSelection();clearTimeout(timer);timer=setTimeout(load,250);});
    search.addEventListener('keydown',event=>{const options=[...results.querySelectorAll('.stock-result')];if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();openResults();activeIndex=event.key==='ArrowDown'?Math.min(activeIndex+1,options.length-1):Math.max(activeIndex-1,0);options.forEach((option,index)=>option.classList.toggle('active',index===activeIndex));options[activeIndex]?.scrollIntoView({block:'nearest'});}else if(event.key==='Enter'&&activeIndex>=0){event.preventDefault();choose(activeIndex);}else if(event.key==='Escape')closeResults();});
    results.addEventListener('click',event=>{const option=event.target.closest('.stock-result');if(option)choose(Number(option.dataset.index));});
    toggle.addEventListener('click',()=>{if(results.hidden){if(entries.length)openResults();else load();}else closeResults();});
    document.addEventListener('click',event=>{if(!event.target.closest('.stock-combobox'))closeResults();},{signal:AppPage.signal});
    addButton.addEventListener('click',()=>{if(selected.value===''){message.textContent='Select an available stock item from the dropdown first.';openResults();return;}const entry=entries[Number(selected.value)],amount=Number(quantity.value);if(!entry)return;if(!Number.isFinite(amount)||amount<=0){message.textContent='Enter a quantity greater than zero.';quantity.focus();return;}if(amount>Number(entry.available)){message.textContent=`Only ${entry.available} ${entry.unit||''} is available.`;quantity.focus();return;}if(!entry.decimal&&!Number.isInteger(amount)){message.textContent='This item requires a whole-number quantity.';quantity.focus();return;}addRow({...entry,quantity:amount});search.value='';resetSelection();load();});
    form.addEventListener('submit',event=>{if(!body.children.length){event.preventDefault();message.textContent='Add at least one stock item.';return;}document.getElementById('save-transfer').disabled=true;});
    AppPage.signal.addEventListener('abort',()=>{controller?.abort();clearTimeout(timer);},{once:true});
    load();
});
</script>
@endpush
