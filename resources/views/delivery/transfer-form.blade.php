@extends('layouts.app')
@section('title', old('direction', request('direction')) === 'unloading' ? 'Unload Vehicle' : 'Load Vehicle')
@section('content')
@include('serials.common')
@php($unloading = old('direction', request('direction')) === 'unloading')

{{-- Hero Header Banner --}}
<div id="transfer-hero" class="mb-5 flex items-center gap-4 rounded-2xl p-5 text-white shadow-md transition-all duration-300" style="background:{{ $unloading ? 'linear-gradient(110deg,#6d28d9,#4f46e5)' : 'linear-gradient(110deg,#047857,#0d9488)' }};">
    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-xl font-black shadow-inner" style="background:rgba(255,255,255,0.22)">
        {{ $unloading ? '↓' : '2' }}
    </span>
    <div>
        <h1 id="transfer-title" class="text-2xl font-extrabold tracking-tight">{{ $unloading ? 'Unloading' : 'Loading' }}</h1>
        <p id="transfer-subtitle" class="text-sm text-white/90">{{ $unloading ? 'Select vehicle stock, then move it safely to a warehouse.' : 'Scan, verify and load goods to vehicle' }}</p>
    </div>
</div>

@if($unloading && request()->routeIs('delivery.unloading'))
    <div class="mb-5 grid gap-3 rounded-2xl border border-violet-100 bg-violet-50/70 p-4 text-sm text-slate-700 sm:grid-cols-4">
        @foreach([['1','Choose vehicle'],['2','Choose warehouse'],['3','Scan or add stock'],['4','Confirm unloading']] as [$step, $label])
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-violet-600 text-xs font-black text-white">{{ $step }}</span>
                <span class="text-xs font-bold">{{ $label }}</span>
            </div>
        @endforeach
    </div>
@endif

<form method="POST" id="delivery-transfer" action="{{ route('delivery.transfers.store') }}">
    @csrf
    <input type="hidden" name="request_key" value="{{ old('request_key', (string) Illuminate\Support\Str::uuid()) }}">

    {{-- Section 1: Loading Operation Details --}}
    <section class="serial-card mb-6 shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 id="operation-title" class="text-lg font-bold text-slate-800 !mb-0">{{ $unloading ? 'Unloading Operation' : 'Loading Operation' }}</h2>
                <p id="operation-subtitle" class="text-xs font-medium text-slate-400">
                    Vehicle Store / {{ $unloading ? 'Unloading' : 'Loading' }} / <span id="ref-preview" class="font-bold text-slate-600">{{ old('reference', request('reference', 'DN-'.date('Ymd').'-001')) }}</span>
                </p>
            </div>
            <span id="operation-badge" class="rounded-full px-3 py-1 text-xs font-bold shadow-xs transition-colors" style="background:{{ $unloading ? '#f3e8ff;color:#6b21a8' : '#d1fae5;color:#065f46' }}">
                In Progress
            </span>
        </div>

        <div class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3">
            {{-- Direction Select --}}
            <div id="direction-container">
                <label for="direction" class="text-xs font-semibold text-slate-600 mb-1 block">Operation *</label>
                <select id="direction" name="direction" required class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-emerald-500">
                    <option value="loading" @selected(old('direction', request('direction')) === 'loading')>Loading: Warehouse → Vehicle</option>
                    <option value="unloading" @selected(old('direction', request('direction')) === 'unloading')>Unloading: Vehicle → Warehouse</option>
                </select>
            </div>

            {{-- Delivery Note No / Reference --}}
            <div>
                <label for="reference" class="text-xs font-semibold text-slate-600 mb-1 block">{{ $unloading ? 'Reference (optional)' : 'Delivery Note No. / Reference' }}</label>
                <input id="reference" name="reference" maxlength="100" value="{{ old('reference', request('reference', 'DN-'.date('Ymd').'-001')) }}" data-auto-reference="{{ 'DN-'.date('Ymd').'-001' }}" placeholder="Type a reference or leave empty" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
            </div>

            {{-- Warehouse --}}
            <div>
                <label for="warehouse_id" class="text-xs font-semibold text-slate-600 mb-1 block">{{ $unloading ? 'Destination Warehouse *' : 'Warehouse *' }}</label>
                <select id="warehouse_id" name="warehouse_id" required data-searchable-select="Search warehouse by name or code" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-emerald-500">
                    <option value="">Select Warehouse</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" data-search="{{ $warehouse->name }} {{ $warehouse->code }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}{{ $warehouse->code ? ' — '.$warehouse->code : '' }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Vehicle No --}}
            <div>
                <label for="vehicle_id" class="text-xs font-semibold text-slate-600 mb-1 block">{{ $unloading ? 'Vehicle to Unload *' : 'Vehicle No. *' }}</label>
                <select id="vehicle_id" name="vehicle_id" required data-searchable-select="Search vehicle number or name" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-emerald-500">
                    <option value="">Select Vehicle</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}"
                                data-driver="{{ $vehicle->drivers->first()?->name ?? 'Unassigned' }}"
                                data-phone="{{ $vehicle->drivers->first()?->phone ?? '' }}"
                                data-search="{{ $vehicle->number }} {{ $vehicle->name }} {{ $vehicle->brand }} {{ $vehicle->model }}"
                                @selected(old('vehicle_id', request('vehicle_id')) == $vehicle->id)>
                            {{ $vehicle->number }} — {{ $vehicle->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Assigned Driver --}}
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Driver</label>
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs">
                    <span id="driver-name" class="font-bold text-slate-800">
                        {{ $vehicles->firstWhere('id', old('vehicle_id', request('vehicle_id')))?->drivers->first()?->name ?? 'Select vehicle first' }}
                    </span>
                    <a id="driver-phone-btn" href="#" class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors" title="Call Driver">
                        <i class="bi bi-telephone-fill text-[10px]"></i>
                    </a>
                </div>
            </div>

            {{-- Assigned Loader --}}
            <div>
                <label class="text-xs font-semibold text-slate-600 mb-1 block">Assigned Loader</label>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-bold text-slate-800">
                    {{ auth()->user()->name ?? 'System Staff' }}
                </div>
            </div>

        </div>
    </section>

    @if($unloading)
    <section id="vehicle-stock-summary" class="serial-card mb-6 hidden rounded-2xl border border-violet-100 bg-white p-5 shadow-sm" aria-live="polite">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-lg font-bold text-slate-800 !mb-0">Stock currently on this vehicle</h2>
                <p id="vehicle-stock-caption" class="text-xs text-slate-500">Select a vehicle to see its live stock balance.</p>
            </div>
            <div class="flex gap-2">
                <span id="vehicle-product-count" class="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-800">0 stock lines</span>
                <span id="vehicle-unit-count" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">0 total units</span>
            </div>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full min-w-[720px] text-left text-xs">
                <thead><tr class="border-b border-slate-200 bg-slate-50 text-slate-600">
                    <th class="p-3">Product / SKU</th><th class="p-3">Tracking details</th><th class="p-3 text-center">Available on vehicle</th><th class="p-3">Unit</th>
                </tr></thead>
                <tbody id="vehicle-stock-summary-body" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </section>
    @endif

    {{-- Section 2: Scan & Load Items Card --}}
    <section class="serial-card mb-6 shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h2 id="stock-section-title" class="text-lg font-bold text-slate-800 !mb-0">{{ $unloading ? 'Select Vehicle Stock' : 'Scan & Load Items' }}</h2>
                <p class="text-xs text-slate-400">{{ $unloading ? 'Scan a barcode or search by product, SKU, lot or serial. Add only the quantity going back to the warehouse.' : 'Scan barcode, SKU, batch or serial to verify and load items.' }}</p>
            </div>
            <span id="line-count" class="rounded-full bg-emerald-100 px-3.5 py-1 text-xs font-bold text-emerald-800">0 items selected</span>
        </div>

        {{-- Barcode Scan Toolbar --}}
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 border border-slate-200">
                <i class="bi bi-barcode text-2xl"></i>
            </div>

            <div class="relative flex-1 min-w-[240px]">
                <input id="stock-search" type="search" role="combobox" aria-autocomplete="list" aria-controls="stock-results" aria-expanded="false" autocomplete="off" placeholder="Scan barcode / QR code here..." maxlength="100" class="w-full rounded-xl border border-slate-300 pl-4 pr-10 py-2.5 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-xs">
                <button type="button" id="stock-toggle" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1" tabindex="-1">
                    <i class="bi bi-chevron-down text-xs"></i>
                </button>
                <input id="stock-option" type="hidden" value="">
                <div id="stock-results" class="stock-results shadow-xl border border-slate-200 rounded-xl mt-1" role="listbox" hidden></div>
            </div>

            <button type="button" id="scan-item" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white shadow-md hover:bg-blue-700 transition-all">
                <i class="bi bi-qr-code-scan text-sm"></i>
                Scan Item
            </button>

        </div>

        {{-- Manual Stock Picker Row --}}
        <div id="manual-picker-container" class="stock-picker-row mb-4 bg-slate-50 p-3.5 rounded-xl border border-slate-200/70 hidden">
            <div class="stock-available-wrap bg-white border border-slate-200 rounded-lg p-2">
                <span class="text-[10px] text-slate-400 uppercase font-bold block">Available Stock</span>
                <strong id="stock-available" class="text-xs font-bold text-emerald-700">—</strong>
            </div>
            <div>
                <label for="stock-quantity" class="text-[11px] font-bold text-slate-600 block mb-1">Quantity</label>
                <input id="stock-quantity" type="number" min="0.0001" max="999999999" step="0.0001" placeholder="0" disabled class="w-full rounded-lg border border-slate-300 p-2 text-xs font-bold text-slate-800">
            </div>
            <div class="flex items-end">
                <button type="button" id="add-stock" disabled class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50 hover:bg-emerald-700 transition-all">
                    + Add Item
                </button>
            </div>
        </div>

        <p id="stock-message" role="status" class="text-xs text-slate-500 mb-4 font-medium">Scan a barcode into the search field or search manually.</p>

        {{-- Table --}}
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="serial-table w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-700 border-b border-slate-200">
                        <th class="w-10 text-center py-3 px-2">
                            <input type="checkbox" id="select-all-lines" checked class="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                        </th>
                        <th class="py-3 px-3 font-bold">SKU</th>
                        <th class="py-3 px-3 font-bold">Product Name</th>
                        <th class="py-3 px-3 font-bold">Batch / Lot</th>
                        <th class="py-3 px-3 font-bold text-center">Available</th>
                        <th class="py-3 px-3 font-bold text-center bg-emerald-50 text-emerald-900 border-x border-emerald-100">Transfer Qty</th>
                        <th class="py-3 px-3 font-bold text-center">Unit</th>
                        <th class="py-3 px-3 font-bold text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="transfer-lines" class="divide-y divide-slate-100 bg-white"></tbody>
            </table>
        </div>
    </section>

    {{-- Section 4: Notes & Action Toolbar --}}
    <div class="mb-6 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <label for="notes" class="text-xs font-semibold text-slate-600 mb-1 block">Notes / Comments</label>
        <textarea id="notes" name="notes" rows="2" maxlength="2000" placeholder="Add optional loading notes..." class="w-full rounded-xl border border-slate-300 p-3 text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500">{{ old('notes') }}</textarea>
    </div>

    {{-- Bottom Actions Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
        <div class="flex items-center gap-2">
            @unless($unloading)
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-xs">
                <i class="bi bi-printer text-slate-500"></i>
                Print Loading List
            </button>
            @endunless
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" id="save-transfer" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-bold text-white shadow-md hover:bg-blue-700 transition-all">
                <i class="bi bi-lock-fill text-sm"></i>
                {{ $unloading ? 'Confirm Unloading' : 'Confirm Loading' }}
            </button>

            <a class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all" href="{{ route('delivery.store') }}">
                Cancel
            </a>
        </div>
    </div>
</form>

<script type="application/json" id="previous-transfer-lines">@json(old('lines', []))</script>
@endsection

@push('scripts')
<style>
.stock-picker-row{display:grid;grid-template-columns:1fr 130px 140px;gap:12px;align-items:end}
.stock-results{position:absolute;z-index:60;top:calc(100% + 6px);left:0;right:0;max-height:300px;overflow-y:auto;border:1px solid #cbd5e1;border-radius:12px;background:#fff;box-shadow:0 16px 35px rgb(15 23 42 / .16)}
.stock-result{display:flex;width:100%;justify-content:space-between;gap:16px;padding:10px 12px;text-align:left;border-bottom:1px solid #f1f5f9;color:#334155}
.stock-result:last-child{border-bottom:0}
.stock-result:hover,.stock-result.active{background:#ecfdf5;color:#047857}
.stock-result-name{min-width:0;font-weight:600;font-size:12px}
.stock-result-stock{flex:none;color:#64748b;font-size:12px}
.stock-result-empty{padding:14px;color:#64748b;font-size:13px}
.stock-serial-group{background:#fafafa;font-weight:700}
.stock-serial-group .stock-result-stock{color:#6d28d9}
.searchable-native-select{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important}
.searchable-select{position:relative}.searchable-select-input{width:100%;border:1px solid #cbd5e1;border-radius:12px;background:#fff;padding:10px 36px 10px 12px;color:#1e293b;font-size:12px;font-weight:600;outline:none}.searchable-select-input:focus{border-color:#10b981;box-shadow:0 0 0 2px rgb(16 185 129 / .2)}
.searchable-select-arrow{pointer-events:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#64748b;font-size:11px}.searchable-select-list{position:absolute;z-index:80;top:calc(100% + 5px);left:0;right:0;max-height:240px;overflow-y:auto;border:1px solid #cbd5e1;border-radius:12px;background:#fff;padding:5px;box-shadow:0 16px 35px rgb(15 23 42 / .18)}.searchable-select-option{display:block;width:100%;border-radius:8px;padding:9px 10px;text-align:left;color:#334155;font-size:12px;font-weight:600}.searchable-select-option:hover,.searchable-select-option.active{background:#ecfdf5;color:#047857}.searchable-select-option.selected{background:#f3e8ff;color:#6d28d9}.searchable-select-empty{padding:12px;text-align:center;color:#94a3b8;font-size:12px}
</style>
<script>
AppPage.ready(() => {
    const form = document.getElementById('delivery-transfer');
    if (!form) return;

    if (@json(request()->routeIs('delivery.loading') || request()->routeIs('delivery.unloading'))) {
        const dirContainer = document.getElementById('direction-container');
        if (dirContainer) dirContainer.hidden = true;
    }

    const sourceFields = ['direction','vehicle_id','warehouse_id'].map(id => document.getElementById(id));
    const search = document.getElementById('stock-search'), selected = document.getElementById('stock-option');
    const results = document.getElementById('stock-results'), toggle = document.getElementById('stock-toggle');
    const available = document.getElementById('stock-available'), quantity = document.getElementById('stock-quantity'), addButton = document.getElementById('add-stock');
    const manualPickerRow = document.getElementById('manual-picker-container');
    const body = document.getElementById('transfer-lines'), message = document.getElementById('stock-message');
    const refInput = document.getElementById('reference'), refPreview = document.getElementById('ref-preview');
    const vehicleSelect = document.getElementById('vehicle_id'), driverName = document.getElementById('driver-name'), driverPhoneBtn = document.getElementById('driver-phone-btn');
    const unloadingPage = document.getElementById('direction').value === 'unloading';
    const stockSummary = document.getElementById('vehicle-stock-summary'), stockSummaryBody = document.getElementById('vehicle-stock-summary-body');

    let entries = [], selectedSerialGroup = null, counter = 0, controller, timer, activeIndex = -1, sourceValues = sourceFields.map(input => input.value);

    function makeSearchableSelect(select) {
        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-select';
        const input = document.createElement('input');
        input.type = 'text'; input.autocomplete = 'off'; input.spellcheck = false;
        input.className = 'searchable-select-input';
        input.placeholder = select.dataset.searchableSelect || 'Search and select';
        input.setAttribute('role', 'combobox'); input.setAttribute('aria-expanded', 'false');
        const arrow = document.createElement('i'); arrow.className = 'bi bi-chevron-down searchable-select-arrow';
        const list = document.createElement('div');
        list.className = 'searchable-select-list'; list.hidden = true; list.setAttribute('role', 'listbox');
        select.parentNode.insertBefore(wrapper, select);
        wrapper.append(input, arrow, list, select);
        select.classList.add('searchable-native-select');
        const options = [...select.options];
        let visible = [], active = -1;

        const sync = () => {
            const option = options.find(item => item.value === select.value);
            input.value = option?.value ? option.textContent.trim() : '';
        };
        const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
        const choose = option => {
            select.value = option.value; sync(); close();
            select.dispatchEvent(new Event('change', {bubbles: true}));
        };
        const render = query => {
            const term = query.trim().toLocaleLowerCase();
            visible = options.filter(option => option.value && (!term || (option.dataset.search || option.textContent).toLocaleLowerCase().includes(term)));
            list.replaceChildren(); active = -1;
            if (!visible.length) {
                const empty = document.createElement('div'); empty.className = 'searchable-select-empty'; empty.textContent = 'No matching records found.'; list.append(empty);
            } else visible.forEach(option => {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'searchable-select-option';
                if (option.value === select.value) button.classList.add('selected');
                button.textContent = option.textContent.trim(); button.setAttribute('role', 'option');
                button.addEventListener('mousedown', event => { event.preventDefault(); choose(option); }); list.append(button);
            });
            list.hidden = false; input.setAttribute('aria-expanded', 'true');
        };
        input.addEventListener('focus', () => { input.select(); render(''); });
        input.addEventListener('click', () => render(input.value === select.selectedOptions[0]?.textContent.trim() ? '' : input.value));
        input.addEventListener('input', () => render(input.value));
        input.addEventListener('keydown', event => {
            const buttons = [...list.querySelectorAll('.searchable-select-option')];
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault(); if (list.hidden) render(input.value);
                active = event.key === 'ArrowDown' ? Math.min(active + 1, buttons.length - 1) : Math.max(active - 1, 0);
                buttons.forEach((button, index) => button.classList.toggle('active', index === active)); buttons[active]?.scrollIntoView({block: 'nearest'});
            } else if (event.key === 'Enter' && (active >= 0 || visible.length === 1)) { event.preventDefault(); choose(visible[active >= 0 ? active : 0]); }
            else if (event.key === 'Escape') { close(); sync(); }
        });
        input.addEventListener('blur', () => { setTimeout(() => { close(); sync(); }, 100); });
        select.addEventListener('invalid', event => { event.preventDefault(); input.focus(); render(input.value); });
        select.addEventListener('searchable:sync', sync); select.addEventListener('change', sync);
        sync();
    }

    document.querySelectorAll('[data-searchable-select]').forEach(makeSearchableSelect);

    // Update Driver Info when Vehicle changes
    function updateDriverInfo() {
        const option = vehicleSelect.options[vehicleSelect.selectedIndex];
        if (option && option.value) {
            const driver = option.getAttribute('data-driver') || 'Unassigned Driver';
            const phone = option.getAttribute('data-phone') || '';
            driverName.textContent = driver;
            if (phone) {
                driverPhoneBtn.href = 'tel:' + phone;
                driverPhoneBtn.classList.remove('opacity-40', 'pointer-events-none');
            } else {
                driverPhoneBtn.href = '#';
                driverPhoneBtn.classList.add('opacity-40', 'pointer-events-none');
            }
        } else {
            driverName.textContent = 'Select vehicle first';
            driverPhoneBtn.href = '#';
            driverPhoneBtn.classList.add('opacity-40', 'pointer-events-none');
        }
    }
    vehicleSelect.addEventListener('change', updateDriverInfo);
    updateDriverInfo();

    // Update Reference Preview
    if (refInput && refPreview) {
        refInput.addEventListener('focus', () => {
            if (refInput.value === refInput.dataset.autoReference) {
                refInput.value = '';
                refPreview.textContent = 'No reference';
            }
        }, { once: true });
        refInput.addEventListener('input', () => {
            refPreview.textContent = refInput.value.trim() || 'No reference';
        });
    }

    function sourceReady() {
        return unloadingPage
            ? Boolean(document.getElementById('direction').value && vehicleSelect.value)
            : sourceFields.every(input => input.value);
    }

    function renderVehicleStockSummary(stockEntries) {
        if (!stockSummary || !stockSummaryBody) return;
        stockSummary.classList.remove('hidden');
        stockSummaryBody.replaceChildren();
        const groups = new Map();
        stockEntries.forEach(entry => {
            const key = [entry.product_id, entry.variant_id || '', entry.lot_id || '', entry.serial_id ? 'serials' : 'stock'].join('|');
            const group = groups.get(key) || {...entry, available: 0, serials: []};
            group.available += Number(entry.available || 0);
            if (entry.serial_number) group.serials.push(entry.serial_number);
            groups.set(key, group);
        });
        const rows = [...groups.values()];
        document.getElementById('vehicle-product-count').textContent = `${rows.length} stock line${rows.length === 1 ? '' : 's'}`;
        const total = rows.reduce((sum, row) => sum + Number(row.available || 0), 0);
        document.getElementById('vehicle-unit-count').textContent = `${total.toLocaleString(undefined, {maximumFractionDigits: 4})} total units`;
        const selectedVehicle = vehicleSelect.options[vehicleSelect.selectedIndex];
        document.getElementById('vehicle-stock-caption').textContent = rows.length
            ? `Live available stock inside ${selectedVehicle?.textContent.trim() || 'the selected vehicle'}.`
            : 'This vehicle currently has no available stock.';
        if (!rows.length) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="4" class="p-6 text-center text-slate-400">No available stock is currently stored on this vehicle.</td>';
            stockSummaryBody.append(row);
            return;
        }
        rows.forEach(entry => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-violet-50/40';
            const tracking = entry.serials.length
                ? `${entry.serials.length} serials: ${entry.serials.join(', ')}`
                : (entry.lot_number ? `Lot: ${entry.lot_number}` : 'Standard stock');
            const cells = [
                `${entry.product_name} (${entry.sku || 'No SKU'})`,
                tracking,
                Number(entry.available).toLocaleString(undefined, {maximumFractionDigits: 4}),
                entry.unit || 'Unit',
            ];
            cells.forEach((value, index) => {
                const cell = document.createElement('td');
                cell.className = `p-3 ${index === 0 ? 'font-bold text-slate-800' : 'text-slate-600'} ${index === 2 ? 'text-center font-extrabold text-emerald-700' : ''}`;
                cell.textContent = value;
                row.append(cell);
            });
            stockSummaryBody.append(row);
        });
    }

    async function loadVehicleStockSummary() {
        if (!unloadingPage || !vehicleSelect.value) {
            stockSummary?.classList.add('hidden');
            return;
        }
        const params = new URLSearchParams({direction: 'unloading', vehicle_id: vehicleSelect.value, q: ''});
        if (document.getElementById('warehouse_id').value) params.set('warehouse_id', document.getElementById('warehouse_id').value);
        document.getElementById('vehicle-stock-caption').textContent = 'Loading live vehicle stock...';
        stockSummary.classList.remove('hidden');
        try {
            const response = await fetch(@json(route('delivery.options')) + '?' + params, {headers: {Accept: 'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to load vehicle stock.');
            renderVehicleStockSummary(data);
        } catch (error) {
            stockSummaryBody.innerHTML = '<tr><td colspan="4" class="p-6 text-center text-rose-600"></td></tr>';
            stockSummaryBody.querySelector('td').textContent = error.message;
        }
    }

    // Refresh the selected-line count and quantity styling using real entered values.
    function updateMetrics() {
        const rows = [...body.querySelectorAll('tr')];
        const count = rows.length;
        document.getElementById('line-count').textContent = `${count} item${count === 1 ? '' : 's'} selected`;
        rows.forEach(row => {
            const qtyInput = row.querySelector('input[name$="[quantity]"]');
            const quantity = parseFloat(qtyInput?.value || '0') || 0;
            if (qtyInput) {
                if (quantity > 0) {
                    qtyInput.className = 'w-20 text-center rounded-lg bg-emerald-100 border border-emerald-300 font-extrabold text-emerald-900 py-1 text-xs focus:ring-2 focus:ring-emerald-500';
                } else {
                    qtyInput.className = 'w-20 text-center rounded-lg bg-white border border-slate-300 font-bold text-slate-800 py-1 text-xs focus:ring-2 focus:ring-emerald-500';
                }
            }
        });
    }

    const updateDirection = () => {
        const unloading = document.getElementById('direction').value === 'unloading';
        document.getElementById('transfer-title').textContent = unloading ? 'Unloading' : 'Loading';
        document.getElementById('transfer-subtitle').textContent = unloading ? 'Select vehicle stock, then move it safely to a warehouse.' : 'Scan, verify and load goods to vehicle';
        document.getElementById('stock-section-title').textContent = unloading ? 'Select Vehicle Stock' : 'Scan & Load Items';
        document.getElementById('operation-title').textContent = unloading ? 'Unloading Operation' : 'Loading Operation';
        document.getElementById('save-transfer').innerHTML = unloading ? '<i class="bi bi-check2-circle text-sm"></i> Confirm Unloading' : '<i class="bi bi-check2-circle text-sm"></i> Confirm Loading';

        const hero = document.getElementById('transfer-hero');
        hero.style.background = unloading ? 'linear-gradient(110deg,#6d28d9,#4f46e5)' : 'linear-gradient(110deg,#047857,#0d9488)';

        const badge = document.getElementById('operation-badge');
        badge.style.background = unloading ? '#f3e8ff' : '#d1fae5';
        badge.style.color = unloading ? '#6b21a8' : '#065f46';
    };

    function addRow(entry) {
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';

        const availableQty = entry.available != null ? Number(entry.available) : 100;
        row.dataset.availableQty = String(availableQty);

        const prefix = `lines[${counter++}]`;

        // Checkbox Cell
        const checkCell = document.createElement('td');
        checkCell.className = 'text-center py-3 px-2';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = true;
        checkbox.className = 'line-checkbox rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4';
        checkbox.addEventListener('change', updateMetrics);
        checkCell.append(checkbox);

        // SKU Cell
        const skuCell = document.createElement('td');
        skuCell.className = 'py-3 px-3 font-extrabold text-slate-800';
        skuCell.textContent = entry.sku || 'P-100' + counter;

        // Product Name Cell
        const nameCell = document.createElement('td');
        nameCell.className = 'py-3 px-3 font-semibold text-slate-800';
        nameCell.textContent = entry.product_name || entry.label || `Product #${entry.product_id}`;

        // Hidden input attributes
        for (const key of ['product_id','variant_id','lot_id','label']) {
            if (entry[key] == null || entry[key] === '') continue;
            const input = document.createElement('input'); input.type = 'hidden'; input.name = `${prefix}[${key}]`; input.value = entry[key]; nameCell.append(input);
        }
        const serialIds = entry.serial_ids || (entry.serial_id ? [entry.serial_id] : []);
        if (serialIds.length) row.dataset.serialIds = serialIds.join(',');
        for (const id of serialIds) {
            const input = document.createElement('input'); input.type='hidden'; input.name=`${prefix}[serial_ids][]`; input.value=id; nameCell.append(input);
        }

        // Batch / Lot Cell
        const batchCell = document.createElement('td');
        batchCell.className = 'py-3 px-3 font-medium text-slate-600';
        batchCell.textContent = serialIds.length > 1
            ? `${serialIds.length} serials selected`
            : (entry.serial_number ? `Serial ${entry.serial_number}` : (entry.lot_number ? entry.lot_number : 'Not lot tracked'));

        // Available quantity is a source-stock fact returned by the backend.
        const availableCell = document.createElement('td');
        availableCell.className = 'py-3 px-3 text-center font-bold text-slate-700';
        availableCell.textContent = row.dataset.availableQty;

        // Loaded Qty Input Cell
        const loadedCell = document.createElement('td');
        loadedCell.className = 'py-2 px-3 text-center bg-emerald-50/50 border-x border-emerald-100';
        const qtyInput = document.createElement('input');
        qtyInput.name = `${prefix}[quantity]`;
        qtyInput.type = 'number';
        qtyInput.required = true;
        qtyInput.min = '0.0001';
        qtyInput.max = '999999999';
        qtyInput.step = entry.decimal ? '0.0001' : '1';
        qtyInput.value = entry.quantity || (serialIds.length ? serialIds.length : '');
        qtyInput.className = 'w-20 text-center rounded-lg bg-emerald-100 border border-emerald-300 font-extrabold text-emerald-900 py-1 text-xs focus:ring-2 focus:ring-emerald-500';
        if (serialIds.length) qtyInput.readOnly = true;
        qtyInput.addEventListener('input', updateMetrics);
        loadedCell.append(qtyInput);

        // Unit Cell
        const unitCell = document.createElement('td');
        unitCell.className = 'py-3 px-3 text-center font-semibold text-slate-500';
        unitCell.textContent = entry.unit || 'Ctn';

        // Action Cell
        const actionCell = document.createElement('td');
        actionCell.className = 'py-3 px-3 text-center';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition-colors';
        removeBtn.innerHTML = '<i class="bi bi-trash text-sm"></i>';
        removeBtn.addEventListener('click', () => {
            row.remove();
            updateMetrics();
        });
        actionCell.append(removeBtn);

        row.append(checkCell, skuCell, nameCell, batchCell, availableCell, loadedCell, unitCell, actionCell);
        body.append(row);
        updateMetrics();
    }

    // Select all checkbox handler
    document.getElementById('select-all-lines')?.addEventListener('change', (e) => {
        const checked = e.target.checked;
        body.querySelectorAll('.line-checkbox').forEach(cb => cb.checked = checked);
        updateMetrics();
    });

    for (const entry of Object.values(JSON.parse(document.getElementById('previous-transfer-lines').textContent))) addRow(entry);

    const closeResults = () => { results.hidden = true; search.setAttribute('aria-expanded','false'); activeIndex = -1; };
    const openResults = () => { if(sourceReady()) { results.hidden = false; search.setAttribute('aria-expanded','true'); } };

    function renderResults() {
        selected.value = ''; activeIndex = -1; results.replaceChildren();
        if(!entries.length) {
            const empty = document.createElement('div');
            empty.className = 'stock-result-empty';
            empty.textContent = 'No available stock matches your search.';
            results.append(empty);
            openResults();
            return;
        }
        const appendOption = (entry, index, container = results) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'stock-result';
            option.setAttribute('role','option');
            option.dataset.index = index;
            const name = document.createElement('span');
            name.className = 'stock-result-name';
            name.textContent = entry.label;
            const stock = document.createElement('span');
            stock.className = 'stock-result-stock';
            stock.textContent = `Available: ${entry.available} ${entry.unit || ''}`;
            option.append(name, stock);
            container.append(option);
        };
        const serialGroupKey = entry => [entry.product_id, entry.variant_id || '', entry.lot_id || ''].join('|');
        const serialGroups = new Map();
        entries.forEach((entry, index) => {
            if (!entry.serial_id) return;
            const key = serialGroupKey(entry);
            const group = serialGroups.get(key) || { entry, records: [] };
            group.records.push({ entry, index });
            serialGroups.set(key, group);
        });
        const renderedSerialGroups = new Set();
        entries.forEach((entry, index) => {
            if (!entry.serial_id) {
                appendOption(entry, index);
                return;
            }
            const key = serialGroupKey(entry);
            if (renderedSerialGroups.has(key)) return;
            renderedSerialGroups.add(key);
            const { records } = serialGroups.get(key);
            const groupButton = document.createElement('button');
            groupButton.type = 'button';
            groupButton.className = 'stock-result stock-serial-group';
            groupButton.dataset.serialGroup = key;
            const name = document.createElement('span');
            name.className = 'stock-result-name';
            name.textContent = `${entry.product_name || entry.label} — click to add one`;
            const count = document.createElement('span');
            count.className = 'stock-result-stock';
            count.textContent = `${records.length} serial${records.length === 1 ? '' : 's'} available`;
            groupButton.append(name, count);
            results.append(groupButton);
        });
        openResults();
    }

    function resetSelection() {
        selected.value = ''; selectedSerialGroup = null; available.textContent = '—'; quantity.value = ''; quantity.disabled = true; quantity.readOnly = false; quantity.step = '0.0001'; quantity.max = '999999999'; addButton.disabled = true;
        manualPickerRow?.classList.add('hidden');
    }

    function choose(index) {
        const entry = entries[index];
        if(!entry) return;
        selected.value = String(index);
        search.value = entry.label;
        available.textContent = `${entry.available} ${entry.unit || ''}`;
        quantity.disabled = false;
        quantity.readOnly = Boolean(entry.serial_id);
        quantity.step = entry.decimal ? '0.0001' : '1';
        quantity.max = String(entry.available);
        quantity.value = entry.serial_id ? '1' : '';
        addButton.disabled = false;
        manualPickerRow?.classList.remove('hidden');
        message.textContent = `Selected: ${entry.label}. Adjust quantity and click + Add Item.`;
        closeResults();
        if(!entry.serial_id) quantity.focus();
    }

    function addSerial(index) {
        const entry = entries[index];
        if (!entry?.serial_id) return;
        if (selectedSerialIds().has(String(entry.serial_id))) {
            message.textContent = 'This serial is already in the selected items.';
            return;
        }
        addRow({...entry, quantity: 1});
        message.textContent = `Added serial ${entry.serial_number}.`;
        search.value = '';
        resetSelection();
        closeResults();
    }

    function selectedSerialIds() {
        return new Set([...body.querySelectorAll('[data-serial-ids]')]
            .flatMap(row => row.dataset.serialIds.split(',').filter(Boolean)));
    }

    function chooseSerialGroup(groupKey) {
        const selectedIds = selectedSerialIds();
        const availableSerials = entries.filter(candidate => candidate.serial_id
            && [candidate.product_id, candidate.variant_id || '', candidate.lot_id || ''].join('|') === groupKey
            && !selectedIds.has(String(candidate.serial_id)));
        if (!availableSerials.length) {
            message.textContent = 'Every available serial for this product is already in the selected items.';
            return;
        }
        selected.value = '';
        selectedSerialGroup = groupKey;
        search.value = availableSerials[0].product_name || availableSerials[0].label;
        available.textContent = `${availableSerials.length} serial${availableSerials.length === 1 ? '' : 's'} available`;
        quantity.disabled = false;
        quantity.readOnly = false;
        quantity.step = '1';
        quantity.max = String(availableSerials.length);
        quantity.value = '1';
        addButton.disabled = false;
        manualPickerRow?.classList.remove('hidden');
        message.textContent = `Enter how many serials to add, up to ${availableSerials.length}.`;
        closeResults();
    }

    async function load() {
        controller?.abort(); controller = new AbortController(); entries = []; resetSelection();
        if(!sourceReady()) {
            results.replaceChildren(); closeResults(); message.textContent = unloadingPage ? 'Select a vehicle first.' : 'Select operation, vehicle and warehouse first.'; return;
        }
        const params = new URLSearchParams(sourceFields.filter(input => input.value).map(input => [input.name, input.value]));
        params.set('q', search.value);
        message.textContent = 'Loading available stock…';
        try {
            const response = await fetch(@json(route('delivery.options')) + '?' + params, { headers: { Accept: 'application/json' }, signal: controller.signal });
            const data = await response.json();
            if(!response.ok) throw new Error(data.message || 'Unable to load stock.');
            entries = data;
            renderResults();
            message.textContent = entries.length ? `${entries.length} available item${entries.length === 1 ? '' : 's'} found. Select one from the list.` : 'No available stock matches this source and search.';
        } catch(error) {
            if(error.name !== 'AbortError') message.textContent = error.message;
        }
    }

    sourceFields.forEach(input => input.addEventListener('change', () => {
        if(body.children.length && !confirm('Changing the source clears selected items. Continue?')) {
            sourceFields.forEach((field, index) => { field.value = sourceValues[index]; field.dispatchEvent(new Event('searchable:sync')); }); return;
        }
        sourceValues = sourceFields.map(field => field.value);
        body.replaceChildren();
        updateMetrics();
        updateDirection();
        search.value = '';
        resetSelection();
        closeResults();
        if (input === vehicleSelect) loadVehicleStockSummary();
        load();
    }));

    if (unloadingPage && vehicleSelect.value) loadVehicleStockSummary();

    search.addEventListener('focus', () => { if(entries.length) openResults(); else load(); });
    search.addEventListener('input', () => { resetSelection(); clearTimeout(timer); timer = setTimeout(load, 250); });

    async function scanCode() {
        const code = search.value.trim();
        if (!code) { search.focus(); message.textContent = 'Scan or enter a barcode, SKU, lot or serial first.'; return; }
        clearTimeout(timer);
        await load();
        const matches = entries.filter(entry => (entry.scan_codes || []).some(value => String(value).trim().toLowerCase() === code.toLowerCase()));
        if (matches.length !== 1) {
            message.textContent = matches.length ? 'Several stock lines match this code. Choose the correct lot or serial from the list.' : 'No exact available stock matches this code. Select a result manually or check warehouse.';
            openResults();
            return;
        }
        const entry = matches[0];
        if (entry.serial_id && selectedSerialIds().has(String(entry.serial_id))) {
            message.textContent = 'This serial is already selected.'; return;
        }
        if (Number(entry.available) < 1) {
            message.textContent = 'This item has no available stock.'; return;
        }
        addRow({...entry, quantity: 1});
        message.textContent = `Scanned ${entry.label}. Added to loaded list!`;
        search.value = ''; resetSelection(); closeResults(); search.focus();
    }

    search.addEventListener('keydown', event => {
        const options = [...results.querySelectorAll('[data-index]')];
        if(event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault(); openResults();
            activeIndex = event.key === 'ArrowDown' ? Math.min(activeIndex + 1, options.length - 1) : Math.max(activeIndex - 1, 0);
            options.forEach((option, index) => option.classList.toggle('active', index === activeIndex));
            options[activeIndex]?.scrollIntoView({block:'nearest'});
        } else if(event.key === 'Enter') {
            event.preventDefault();
            if(activeIndex < 0) { scanCode(); }
            else if(activeIndex >= 0) {
                const index = Number(options[activeIndex]?.dataset.index);
                entries[index]?.serial_id ? addSerial(index) : choose(index);
            }
            else if(entries.length === 1) choose(0);
            else if(entries.length > 1) openResults();
        } else if(event.key === 'Escape') closeResults();
    });

    document.getElementById('scan-item')?.addEventListener('click', () => {
        search.focus();
        if(search.value.trim()) scanCode();
        else message.textContent = 'Scanner ready. Point barcode scanner to search field.';
    });

    results.addEventListener('click', event => {
        const group = event.target.closest('.stock-serial-group');
        if (group) {
            chooseSerialGroup(group.dataset.serialGroup);
            return;
        }
        const option = event.target.closest('[data-index]');
        if(option) {
            const index = Number(option.dataset.index);
            entries[index]?.serial_id ? addSerial(index) : choose(index);
        }
    });

    toggle.addEventListener('click', () => {
        if(results.hidden) { if(entries.length) openResults(); else load(); }
        else closeResults();
    });

    document.addEventListener('click', event => {
        if(!event.target.closest('.stock-combobox')) closeResults();
    }, {signal: AppPage.signal});

    addButton.addEventListener('click', () => {
        const amount = Number(quantity.value);
        if (selectedSerialGroup) {
            const selectedIds = selectedSerialIds();
            const serials = entries.filter(candidate => candidate.serial_id
                && [candidate.product_id, candidate.variant_id || '', candidate.lot_id || ''].join('|') === selectedSerialGroup
                && !selectedIds.has(String(candidate.serial_id)));
            if (!Number.isInteger(amount) || amount < 1 || amount > serials.length) {
                message.textContent = `Enter a whole number from 1 to ${serials.length}.`;
                quantity.focus();
                return;
            }
            const chosen = serials.slice(0, amount);
            addRow({...chosen[0], serial_id: null, serial_ids: chosen.map(item => item.serial_id), serial_number: chosen.map(item => item.serial_number).join(', '), quantity: amount});
            search.value = '';
            resetSelection();
            load();
            return;
        }
        if(selected.value === '') { message.textContent = 'Select an available stock item from the dropdown first.'; openResults(); return; }
        const entry = entries[Number(selected.value)];
        if(!entry) return;
        if(!Number.isFinite(amount) || amount <= 0) { message.textContent = 'Enter a quantity greater than zero.'; quantity.focus(); return; }
        if(amount > Number(entry.available)) { message.textContent = `Only ${entry.available} ${entry.unit || ''} is available.`; quantity.focus(); return; }
        if(!entry.decimal && !Number.isInteger(amount)) { message.textContent = 'This item requires a whole-number quantity.'; quantity.focus(); return; }
        addRow({...entry, quantity: amount});
        search.value = ''; resetSelection(); load();
    });

    form.addEventListener('submit', event => {
        if(!body.children.length) {
            event.preventDefault();
            message.textContent = 'Add at least one stock item before confirming.';
            return;
        }
        document.getElementById('save-transfer').disabled = true;
    });

    AppPage.signal.addEventListener('abort', () => {
        controller?.abort(); clearTimeout(timer);
    }, {once: true});

    updateMetrics();
    updateDirection();
    load();
});
</script>
@endpush
