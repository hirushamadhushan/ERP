@extends('layouts.app')
@section('title','Vehicle Store — Inventory & Transfers')
@section('content')
@include('serials.common')
@include('delivery.header', ['activeStep' => 2])

<div class="mb-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-5">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900">Vehicle Store Stock</h2>
            <p class="text-xs text-slate-500">Load goods onto a vehicle from a warehouse or unload remaining stock back to a warehouse</p>
        </div>
        @if(auth()->user()->canUseDelivery('delivery.transfer'))
            <div class="flex items-center gap-2">
                @if($activeDelivery)
                    <a class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-purple-700 transition-all" href="{{ route('delivery.consignments.show', $activeDelivery) }}">
                        <i class="bi bi-arrow-right-circle"></i> Continue {{ $activeDelivery->number }}
                    </a>
                @else
                    <a class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-all" href="{{ route('delivery.loading', ['vehicle_id' => $vehicle?->id]) }}">
                        <i class="bi bi-box-arrow-up"></i> ↑ Loading
                    </a>
                    @if(auth()->user()->canUseDelivery('delivery.unload'))<a class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-purple-700 transition-all" href="{{ route('delivery.unloading', ['vehicle_id' => $vehicle?->id]) }}">
                        <i class="bi bi-box-arrow-down"></i> ↓ Unloading
                    </a>@endif
                @endif
            </div>
        @endif
    </div>

    @if($activeDelivery)
        <div class="mb-4 rounded-xl border border-violet-200 bg-violet-50 p-3 text-sm text-violet-900">
            <strong>{{ $activeDelivery->number }}</strong> for {{ $activeDelivery->customer->name }} is {{ str_replace('_', ' ', $activeDelivery->status) }}. Complete this delivery before moving other stock on this vehicle.
        </div>
    @endif

    <form method="GET" class="grid gap-4 sm:grid-cols-3 items-end mb-4">
        <div>
            <label for="vehicle_id" class="block text-xs font-bold text-slate-600 mb-1">Select Vehicle</label>
            <select name="vehicle_id" id="vehicle_id" data-vehicle-search class="w-full rounded-xl border-slate-200 text-xs font-semibold text-slate-800 shadow-xs focus:border-purple-500 focus:ring-purple-500">
                <option value="">Select a vehicle to inspect live stock</option>
                @foreach($vehicles as $item)
                    <option value="{{ $item->id }}" data-search="{{ $item->number }} {{ $item->name }} {{ $item->brand }} {{ $item->model }}" @selected($vehicle?->id===$item->id)>{{ $item->number }} — {{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="q" class="block text-xs font-bold text-slate-600 mb-1">Search Product / SKU</label>
            <input id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search stock by name or code..." class="w-full rounded-xl border-slate-200 text-xs shadow-xs focus:border-purple-500 focus:ring-purple-500">
        </div>
        <div>
            <button class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-indigo-700 transition-all">
                <i class="bi bi-search"></i> Show Stock
            </button>
        </div>
    </form>

    @if($vehicle)
        <div class="mb-4 flex flex-wrap items-center justify-between rounded-xl bg-purple-50/70 border border-purple-100 p-3 text-xs text-purple-900">
            <div>
                Driver: <strong class="font-bold">{{ $vehicle->drivers->first()?->name ?? 'Unassigned' }}</strong> · Vehicle Store Location: <strong class="font-bold">{{ $vehicle->stores->first()?->name }}</strong>
            </div>
            <span class="text-[11px] text-purple-600 font-semibold">Live inventory balance</span>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table id="vehicle-stock-table" class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-600 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                        <th class="p-3">Product / SKU</th>
                        <th class="p-3">Variation</th>
                        <th class="p-3 text-center">On Vehicle</th>
                        <th class="p-3 text-right">Stock Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($balances as $row)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-3 font-semibold text-slate-900">{{ $row['product']->name }} <span class="text-slate-400">({{ $row['product']->code }})</span></td>
                            <td class="p-3 text-slate-500">{{ $row['variation'] }}</td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-800">
                                    {{ number_format($row['stock'], 4) }} {{ $row['product']->unit?->short_name }}
                                </span>
                            </td>
                            <td class="p-3 text-right font-bold text-slate-800">Rs {{ number_format($row['purchase_value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-6 text-center text-slate-400">No vehicle stock found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $products->links() }}</div>
    @endif
</div>

<div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
    <h3 class="text-base font-extrabold text-slate-900 mb-1">Transfer History</h3>
    <p class="mb-4 text-xs text-slate-500">Historical warehouse loading and vehicle unloading operations</p>

    <div class="overflow-x-auto rounded-xl border border-slate-200">
        <table id="delivery-history-table" class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-slate-50 text-slate-600 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                    <th class="p-3">Transfer</th>
                    <th class="p-3">Date</th>
                    <th class="p-3">Operation</th>
                    <th class="p-3">Vehicle</th>
                    <th class="p-3">Warehouse</th>
                    <th class="p-3">Driver</th>
                    <th class="p-3">Reference</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
                @foreach($transfers as $transfer)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-3 font-bold text-indigo-600">
                            <a href="{{ route('delivery.transfers.show', $transfer) }}" class="hover:underline">DLV-{{ $transfer->id }}</a>
                        </td>
                        <td class="p-3 text-slate-500 whitespace-nowrap">{{ $transfer->created_at->format('Y-m-d H:i') }}</td>
                        <td class="p-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold {{ $transfer->direction === 'loading' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800' }}">
                                {{ ucfirst($transfer->direction) }}
                            </span>
                        </td>
                        <td class="p-3 font-semibold text-slate-800">{{ $transfer->vehicle->number }}</td>
                        <td class="p-3 text-slate-600">{{ $transfer->warehouse->name }}</td>
                        <td class="p-3 text-slate-600">{{ $transfer->driver?->name ?? '—' }}</td>
                        <td class="p-3 font-mono text-[11px] text-slate-500">{{ $transfer->transaction->reference ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transfers->links() }}</div>
</div>

@php
$deliveryTables = [['id'=>'delivery-history-table','title'=>'Delivery Transfer History','exportColumns'=>[0,1,2,3,4,5,6],'nonOrderable'=>[],'order'=>[[1,'desc']],'emptyTable'=>'No transfers recorded']];
if($vehicle) array_unshift($deliveryTables, ['id'=>'vehicle-stock-table','title'=>'Vehicle Stock - '.$vehicle->number,'exportColumns'=>[0,1,2,3],'nonOrderable'=>[],'order'=>[[0,'asc']],'emptyTable'=>'No vehicle stock found']);
@endphp
@include('delivery.table-tools', compact('deliveryTables'))
@endsection

@push('scripts')
<style>
.store-search-native{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important}.store-search-wrap{position:relative}.store-search-input{width:100%;border:1px solid #cbd5e1;border-radius:12px;background:#fff;padding:10px 36px 10px 12px;color:#1e293b;font-size:12px;font-weight:600;outline:none}.store-search-input:focus{border-color:#7c3aed;box-shadow:0 0 0 2px rgb(124 58 237 / .18)}.store-search-arrow{pointer-events:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#64748b;font-size:11px}.store-search-list{position:absolute;z-index:80;top:calc(100% + 5px);left:0;right:0;max-height:240px;overflow-y:auto;border:1px solid #cbd5e1;border-radius:12px;background:#fff;padding:5px;box-shadow:0 16px 35px rgb(15 23 42 / .18)}.store-search-option{display:block;width:100%;border-radius:8px;padding:9px 10px;text-align:left;color:#334155;font-size:12px;font-weight:600}.store-search-option:hover,.store-search-option.active{background:#f3e8ff;color:#6d28d9}.store-search-option.selected{background:#ede9fe;color:#6d28d9}.store-search-empty{padding:12px;text-align:center;color:#94a3b8;font-size:12px}
</style>
<script>
AppPage.ready(() => {
    const select = document.querySelector('[data-vehicle-search]');
    if (!select || select.dataset.enhanced) return;
    select.dataset.enhanced = 'true';
    const wrap = document.createElement('div'), input = document.createElement('input'), arrow = document.createElement('i'), list = document.createElement('div');
    wrap.className = 'store-search-wrap'; input.type = 'text'; input.autocomplete = 'off'; input.placeholder = 'Search vehicle number or name'; input.className = 'store-search-input'; input.setAttribute('role','combobox'); input.setAttribute('aria-expanded','false'); arrow.className = 'bi bi-chevron-down store-search-arrow'; list.className = 'store-search-list'; list.hidden = true; list.setAttribute('role','listbox');
    select.parentNode.insertBefore(wrap, select); wrap.append(input, arrow, list, select); select.classList.add('store-search-native');
    const options = [...select.options]; let visible = [], active = -1;
    const sync = () => { const option = options.find(item => item.value === select.value); input.value = option?.value ? option.textContent.trim() : ''; };
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded','false'); active = -1; };
    const choose = option => { select.value = option.value; sync(); close(); select.dispatchEvent(new Event('change',{bubbles:true})); };
    const render = value => {
        const query = value.trim().toLocaleLowerCase(); visible = options.filter(option => option.value && (!query || (option.dataset.search || option.textContent).toLocaleLowerCase().includes(query))); list.replaceChildren(); active = -1;
        if (!visible.length) { const empty = document.createElement('div'); empty.className = 'store-search-empty'; empty.textContent = 'No matching vehicles found.'; list.append(empty); }
        else visible.forEach(option => { const button = document.createElement('button'); button.type = 'button'; button.className = 'store-search-option' + (option.value === select.value ? ' selected' : ''); button.textContent = option.textContent.trim(); button.addEventListener('mousedown', event => { event.preventDefault(); choose(option); }); list.append(button); });
        list.hidden = false; input.setAttribute('aria-expanded','true');
    };
    input.addEventListener('focus', () => { input.select(); render(''); }); input.addEventListener('input', () => render(input.value));
    input.addEventListener('keydown', event => { const buttons = [...list.querySelectorAll('.store-search-option')]; if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); if (list.hidden) render(input.value); active = event.key === 'ArrowDown' ? Math.min(active + 1, buttons.length - 1) : Math.max(active - 1, 0); buttons.forEach((button,index) => button.classList.toggle('active',index === active)); buttons[active]?.scrollIntoView({block:'nearest'}); } else if (event.key === 'Enter' && (active >= 0 || visible.length === 1)) { event.preventDefault(); choose(visible[active >= 0 ? active : 0]); } else if (event.key === 'Escape') { close(); sync(); } });
    input.addEventListener('blur', () => setTimeout(() => { close(); sync(); }, 100)); sync();
});
</script>
@endpush
