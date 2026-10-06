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
        <p id="transfer-subtitle" class="text-sm text-white/90">{{ $unloading ? 'Move remaining vehicle stock back to a warehouse.' : 'Scan, verify and load goods to vehicle' }}</p>
    </div>
</div>

<form method="POST" id="delivery-transfer" action="{{ route('delivery.transfers.store') }}">
    @csrf
    <input type="hidden" name="request_key" value="{{ old('request_key', (string) Illuminate\Support\Str::uuid()) }}">
    @if($returnNote)<input type="hidden" name="return_id" value="{{ $returnNote->id }}">@endif

    {{-- Section 1: Loading Operation Details --}}
    <section class="serial-card mb-6 shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 id="operation-title" class="text-lg font-bold text-slate-800 !mb-0">{{ $unloading ? 'Unloading Operation' : 'Loading Operation' }}</h2>
                <p id="operation-subtitle" class="text-xs font-medium text-slate-400">
                    Delivery / Loading / <span id="ref-preview" class="font-bold text-slate-600">{{ old('reference', request('reference', 'DN-'.date('Ymd').'-001')) }}</span>
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
                @if($returnNote)<input type="hidden" name="direction" value="unloading">@endif
                <select id="direction" name="direction" required @disabled($returnNote) class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-emerald-500">
                    <option value="loading" @selected(old('direction', request('direction')) === 'loading')>Loading: Warehouse → Vehicle</option>
                    <option value="unloading" @selected(old('direction', request('direction')) === 'unloading')>Unloading: Vehicle → Warehouse</option>
                </select>
            </div>

            {{-- Delivery Note No / Reference --}}
            <div>
                <label for="reference" class="text-xs font-semibold text-slate-600 mb-1 block">Delivery Note No. / Reference</label>
                <input id="reference" name="reference" maxlength="100" value="{{ old('reference', request('reference', 'DN-'.date('Ymd').'-001')) }}" @readonly($returnNote) placeholder="DN-20250417-001" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
            </div>

            {{-- Warehouse --}}
            <div>
                <label for="warehouse_id" class="text-xs font-semibold text-slate-600 mb-1 block">Warehouse *</label>
                <select id="warehouse_id" name="warehouse_id" required class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-emerald-500">
                    <option value="">Select Warehouse</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Vehicle No --}}
            <div>
                <label for="vehicle_id" class="text-xs font-semibold text-slate-600 mb-1 block">Vehicle No. *</label>
                @if($returnNote)<input type="hidden" name="vehicle_id" value="{{ request('vehicle_id') }}">@endif
                <select id="vehicle_id" name="vehicle_id" required @disabled($returnNote) class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-emerald-500">
                    <option value="">Select Vehicle</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}"
                                data-driver="{{ $vehicle->drivers->first()?->name ?? 'Unassigned' }}"
                                data-phone="{{ $vehicle->drivers->first()?->phone ?? '' }}"
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

    @if($returnNote)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>{{ $returnNote->number }}</strong> is locked to its recorded returned products, quantities, lots and serials. Select the receiving warehouse and confirm unloading.
        </div>
    @endif

    {{-- Section 2: Scan & Load Items Card --}}
    <section class="serial-card mb-6 shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h2 id="stock-section-title" class="text-lg font-bold text-slate-800 !mb-0">{{ $unloading ? 'Select Vehicle Stock' : 'Scan & Load Items' }}</h2>
                <p class="text-xs text-slate-400">Scan barcode, SKU, batch or serial to verify and load items.</p>
            </div>
            <span id="line-count" class="rounded-full bg-emerald-100 px-3.5 py-1 text-xs font-bold text-emerald-800">0 items selected</span>
        </div>

        {{-- Barcode Scan Toolbar --}}
        <div class="mb-4 flex flex-wrap items-center gap-3 {{ $returnNote ? 'hidden' : '' }}">
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

            <button type="button" id="add-manually" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition-all">
                <i class="bi bi-plus-square text-sm"></i>
                Add Manually
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

<script type="application/json" id="previous-transfer-lines">@json(old('lines', $returnItems))</script>
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
</style>
<script>
AppPage.ready(() => {
    const form = document.getElementById('delivery-transfer');
    if (!form) return;
    const lockedReturn = @json((bool) $returnNote);

    if (@json(request()->routeIs('delivery.loading'))) {
        const dirContainer = document.getElementById('direction-container');
        if (dirContainer) dirContainer.hidden = true;
    }

    const sourceFields = ['direction','vehicle_id','warehouse_id'].map(id => document.getElementById(id));
    const search = document.getElementById('stock-search'), selected = document.getElementById('stock-option');
    const results = document.getElementById('stock-results'), toggle = document.getElementById('stock-toggle');
    const available = document.getElementById('stock-available'), quantity = document.getElementById('stock-quantity'), addButton = document.getElementById('add-stock');
    const body = document.getElementById('transfer-lines'), message = document.getElementById('stock-message');
    const refInput = document.getElementById('reference'), refPreview = document.getElementById('ref-preview');
    const vehicleSelect = document.getElementById('vehicle_id'), driverName = document.getElementById('driver-name'), driverPhoneBtn = document.getElementById('driver-phone-btn');

    let entries = [], counter = 0, controller, timer, activeIndex = -1, sourceValues = sourceFields.map(input => input.value);

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
        refInput.addEventListener('input', () => {
            refPreview.textContent = refInput.value.trim() || 'DN-001';
        });
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
        document.getElementById('transfer-subtitle').textContent = unloading ? 'Move remaining vehicle stock back to a warehouse.' : 'Scan, verify and load goods to vehicle';
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

        if (entry.serial_id) row.dataset.serialId = String(entry.serial_id);

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
        for (const id of serialIds) {
            const input = document.createElement('input'); input.type='hidden'; input.name=`${prefix}[serial_ids][]`; input.value=id; nameCell.append(input);
        }

        // Batch / Lot Cell
        const batchCell = document.createElement('td');
        batchCell.className = 'py-3 px-3 font-medium text-slate-600';
        batchCell.textContent = entry.serial_number ? `Serial ${entry.serial_number}` : (entry.lot_number ? entry.lot_number : (entry.batch || `B240${10 + counter}`));

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
        if (serialIds.length || lockedReturn) qtyInput.readOnly = true;
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
        if (!lockedReturn) actionCell.append(removeBtn);
        else actionCell.textContent = 'Return';

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
    const openResults = () => { if(sourceFields.every(input => input.value)) { results.hidden = false; search.setAttribute('aria-expanded','true'); } };

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
        entries.forEach((entry, index) => {
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
            results.append(option);
        });
        openResults();
    }

    function resetSelection() {
        selected.value = ''; available.textContent = '—'; quantity.value = ''; quantity.disabled = true; quantity.readOnly = false; quantity.step = '0.0001'; quantity.max = '999999999'; addButton.disabled = true;
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
        message.textContent = `Selected: ${entry.label}. Adjust quantity and click + Add Item.`;
        closeResults();
        if(!entry.serial_id) quantity.focus();
    }

    async function load() {
        controller?.abort(); controller = new AbortController(); entries = []; resetSelection();
        if(sourceFields.some(input => !input.value)) {
            results.replaceChildren(); closeResults(); message.textContent = 'Select operation, vehicle and warehouse first.'; return;
        }
        const params = new URLSearchParams(sourceFields.map(input => [input.name, input.value]));
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
            sourceFields.forEach((field, index) => field.value = sourceValues[index]); return;
        }
        sourceValues = sourceFields.map(field => field.value);
        body.replaceChildren();
        updateMetrics();
        updateDirection();
        search.value = '';
        resetSelection();
        closeResults();
        load();
    }));

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
        if (entry.serial_id && body.querySelector(`tr[data-serial-id="${entry.serial_id}"]`)) {
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
        const options = [...results.querySelectorAll('.stock-result')];
        if(event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault(); openResults();
            activeIndex = event.key === 'ArrowDown' ? Math.min(activeIndex + 1, options.length - 1) : Math.max(activeIndex - 1, 0);
            options.forEach((option, index) => option.classList.toggle('active', index === activeIndex));
            options[activeIndex]?.scrollIntoView({block:'nearest'});
        } else if(event.key === 'Enter') {
            event.preventDefault();
            if(document.getElementById('direction').value === 'loading' && activeIndex < 0) { scanCode(); }
            else if(activeIndex >= 0) choose(activeIndex);
            else if(entries.length === 1) choose(0);
            else if(entries.length > 1) openResults();
        } else if(event.key === 'Escape') closeResults();
    });

    document.getElementById('scan-item')?.addEventListener('click', () => {
        search.focus();
        if(search.value.trim()) scanCode();
        else message.textContent = 'Scanner ready. Point barcode scanner to search field.';
    });

    const manualPickerRow = document.getElementById('manual-picker-container');
    document.getElementById('add-manually')?.addEventListener('click', () => {
        if (manualPickerRow) manualPickerRow.classList.toggle('hidden');
        search.focus();
        if(entries.length) openResults(); else load();
    });

    results.addEventListener('click', event => {
        const option = event.target.closest('.stock-result');
        if(option) choose(Number(option.dataset.index));
    });

    toggle.addEventListener('click', () => {
        if(results.hidden) { if(entries.length) openResults(); else load(); }
        else closeResults();
    });

    document.addEventListener('click', event => {
        if(!event.target.closest('.stock-combobox')) closeResults();
    }, {signal: AppPage.signal});

    addButton.addEventListener('click', () => {
        if(selected.value === '') { message.textContent = 'Select an available stock item from the dropdown first.'; openResults(); return; }
        const entry = entries[Number(selected.value)], amount = Number(quantity.value);
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
