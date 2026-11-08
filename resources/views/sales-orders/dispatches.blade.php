@extends('layouts.app')
@section('title', 'Dispatches | Ant')

@section('content')
<link rel="stylesheet" href="{{ asset('css/sales-dispatches.css') }}?v={{ filemtime(public_path('css/sales-dispatches.css')) }}">

<div class="dispatches-page">
    <!-- Top Breadcrumb Bar -->
    <div class="dispatches-breadcrumb-bar">
        <h1>Dispatches</h1>
        <nav class="dispatches-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('sales.index') }}" style="color: inherit; text-decoration: none;">Sales</a>
            <span class="sep">&rsaquo;</span>
            <span class="current">Dispatches</span>
        </nav>
    </div>

    <!-- Main Container Card -->
    <div class="dispatches-card">
        <!-- Top Controls: Show entries & Search -->
        <div class="dispatches-toolbar">
            <div class="dispatches-entries">
                <span>Show</span>
                <select id="per-page-select" aria-label="Entries per page">
                    <option value="10" @selected($perPage == 10)>10</option>
                    <option value="25" @selected($perPage == 25)>25</option>
                    <option value="50" @selected($perPage == 50)>50</option>
                    <option value="100" @selected($perPage == 100)>100</option>
                </select>
                <span>entries</span>
            </div>

            <div class="dispatches-search-wrap">
                <input type="text" id="dispatch-search" placeholder="Search... (DNo/ONo/InvNo)" value="{{ request('q') }}" aria-label="Search dispatches" autocomplete="off">
                <i class="bi bi-search search-icon" aria-hidden="true"></i>
            </div>
        </div>

        <!-- Dispatches Table -->
        <div class="dispatches-table-scroll">
            <table class="dispatches-table" id="dispatches-list-table">
                <thead>
                    <tr>
                        <th>DISPATCH NO <span class="sort-arrows">&#9650;&#9660;</span></th>
                        <th>ORDER NO <span class="sort-arrows">&#9650;&#9660;</span></th>
                        <th>INVOICE NO <span class="sort-arrows">&#9650;&#9660;</span></th>
                        <th>DISPATCH DATE <span class="sort-arrows">&#9650;&#9660;</span></th>
                        <th>STATUS <span class="sort-arrows">&#9650;&#9660;</span></th>
                        <th style="width: 100px;">ACTION</th>
                    </tr>
                </thead>
                <tbody id="dispatches-table-body">
                    @include('sales-orders.partials.dispatches-rows')
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <div class="dispatches-pagination" id="dispatches-pagination-container">
            @include('sales-orders.partials.dispatches-pagination')
        </div>
    </div>
</div>

<!-- ================= DISPATCH NOTE MODAL (Image 3) ================= -->
<div id="dispatch-modal-backdrop" class="dispatch-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="modal-dispatch-title">
    <div class="dispatch-modal-card">
        <!-- Modal Header -->
        <div class="dispatch-modal-header">
            <h3 id="modal-dispatch-title">Dispatch Note : —</h3>
            <button type="button" id="btn-modal-x" class="dispatch-modal-close" aria-label="Close dialog">&times;</button>
        </div>

        <!-- Modal Body -->
        <div class="dispatch-modal-body">
            <!-- Metadata Grid with Dotted Lines -->
            <div class="dispatch-meta-grid">
                <div class="dispatch-meta-item">
                    <span class="dispatch-meta-label">Order No</span>
                    <span class="dispatch-meta-value" id="modal-order-no"></span>
                </div>
                <div class="dispatch-meta-item">
                    <span class="dispatch-meta-label">Invoice No</span>
                    <span class="dispatch-meta-value" id="modal-invoice-no"></span>
                </div>
                <div class="dispatch-meta-item">
                    <span class="dispatch-meta-label">Invoice Date</span>
                    <span class="dispatch-meta-value" id="modal-invoice-date"></span>
                </div>
                <div class="dispatch-meta-item" style="grid-column: span 1.5;">
                    <span class="dispatch-meta-label">Sales Person</span>
                    <span class="dispatch-meta-value" id="modal-sales-person"></span>
                </div>
                <div class="dispatch-meta-item" style="grid-column: span 1.5;">
                    <span class="dispatch-meta-label">Customer</span>
                    <span class="dispatch-meta-value" id="modal-customer"></span>
                </div>
            </div>

            <!-- Items Table -->
            <div class="dispatch-modal-table-wrap">
                <table class="dispatch-modal-table">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th style="width: 140px;">Inv Qty</th>
                            <th style="width: 140px;">Qty</th>
                        </tr>
                    </thead>
                    <tbody id="modal-items-tbody">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>

            <!-- Vehicle Row -->
            <div class="dispatch-vehicle-row">
                <label for="modal-vehicle-select">Delivery Vehicle</label>
                <select id="modal-vehicle-select" name="delivery_vehicle_id">
                    <option value="">Select vehicle...</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->number }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Modal Action Buttons -->
            <div class="dispatch-modal-footer">
                <button type="button" id="btn-modal-save" class="btn-dispatch-save">Save</button>
                <button type="button" id="btn-modal-close" class="btn-dispatch-close">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    let currentDispatchId = null;
    const modal = document.getElementById('dispatch-modal-backdrop');
    const modalTitle = document.getElementById('modal-dispatch-title');
    const orderNo = document.getElementById('modal-order-no');
    const invoiceNo = document.getElementById('modal-invoice-no');
    const invoiceDate = document.getElementById('modal-invoice-date');
    const salesPerson = document.getElementById('modal-sales-person');
    const customer = document.getElementById('modal-customer');
    const vehicleSelect = document.getElementById('modal-vehicle-select');
    const itemsTbody = document.getElementById('modal-items-tbody');
    const saveBtn = document.getElementById('btn-modal-save');
    const closeBtn = document.getElementById('btn-modal-close');
    const xBtn = document.getElementById('btn-modal-x');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    function showToast(message, isSuccess = true) {
        const toast = document.createElement('div');
        toast.className = `dispatch-toast ${isSuccess ? 'is-success' : ''}`;
        toast.innerHTML = `<i class="bi ${isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'}"></i><span>${message}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function closeModal() {
        modal.classList.add('hidden');
        currentDispatchId = null;
    }

    async function openModal(dispatchId) {
        currentDispatchId = dispatchId;
        itemsTbody.replaceChildren();
        modalTitle.textContent = 'Loading dispatch...';
        orderNo.textContent = '';
        invoiceNo.textContent = '';
        invoiceDate.textContent = '';
        salesPerson.textContent = '';
        customer.textContent = '';
        vehicleSelect.value = '';

        modal.classList.remove('hidden');

        try {
            const res = await fetch(`/sales/dispatches/${dispatchId}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('Failed to load dispatch details.');
            const data = await res.json();

            // Image 3: "Dispatch Note : 241" (stripping leading 0 or showing number)
            const numDisplay = String(data.dispatch_number).replace(/^0+/, '') || data.dispatch_number;
            modalTitle.textContent = `Dispatch Note : ${numDisplay}`;

            orderNo.textContent = data.order_number || '';
            invoiceNo.textContent = data.invoice_number || '';
            invoiceDate.textContent = data.invoice_date || '';
            salesPerson.textContent = data.salesperson_name || '';
            customer.textContent = data.customer_name || '';

            if (data.delivery_vehicle_id) {
                vehicleSelect.value = data.delivery_vehicle_id;
            } else if (data.vehicle_number) {
                const opt = [...vehicleSelect.options].find(o => o.text.trim() === data.vehicle_number.trim());
                if (opt) vehicleSelect.value = opt.value;
            }

            itemsTbody.replaceChildren();
            (data.items || []).forEach(item => {
                const tr = document.createElement('tr');
                tr.dataset.itemId = item.id;
                tr.innerHTML = `
                    <td class="font-bold" style="color: #334155;">${item.item_code || ''}</td>
                    <td style="color: #334155;">${item.item_name || ''}</td>
                    <td>
                        <input type="text" readonly class="dispatch-inv-qty-box" value="${Number(item.inv_qty)}">
                    </td>
                    <td>
                        <input type="number" min="0" step="any" class="dispatch-qty-input modal-item-qty" value="${Number(item.qty)}">
                    </td>
                `;
                itemsTbody.appendChild(tr);
            });
        } catch (err) {
            console.error(err);
            showToast('Unable to load dispatch note details.', false);
            closeModal();
        }
    }

    async function saveDispatch() {
        if (!currentDispatchId) return;

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const items = [];
        itemsTbody.querySelectorAll('tr').forEach(tr => {
            const id = tr.dataset.itemId;
            const qty = parseFloat(tr.querySelector('.modal-item-qty')?.value || 0);
            if (id) items.push({ id, qty });
        });

        try {
            const res = await fetch(`/sales/dispatches/${currentDispatchId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    delivery_vehicle_id: vehicleSelect.value || null,
                    items
                })
            });

            const result = await res.json();
            if (!res.ok) throw new Error(result.message || 'Failed to update dispatch note.');

            showToast(result.message || 'Dispatch note saved successfully.');
            closeModal();
        } catch (err) {
            console.error(err);
            showToast(err.message || 'Error saving dispatch note.', false);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
        }
    }

    const searchInput = document.getElementById('dispatch-search');
    const tableBody = document.getElementById('dispatches-table-body');
    const paginationContainer = document.getElementById('dispatches-pagination-container');
    const perPageSelect = document.getElementById('per-page-select');

    let debounceTimer = null;
    let currentAbortController = null;

    function handleViewClick(e) {
        const id = e.currentTarget.dataset.id;
        if (id) openModal(id);
    }

    function bindViewButtons() {
        document.querySelectorAll('.btn-dispatch-view').forEach(btn => {
            btn.removeEventListener('click', handleViewClick);
            btn.addEventListener('click', handleViewClick);
        });
    }

    function bindPaginationLinks() {
        if (!paginationContainer) return;
        paginationContainer.querySelectorAll('a.page-btn').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const url = new URL(link.href);
                const page = url.searchParams.get('page') || 1;
                fetchDispatches(page);
            });
        });
    }

    async function fetchDispatches(page = 1) {
        if (currentAbortController) {
            currentAbortController.abort();
        }
        currentAbortController = new AbortController();

        const query = searchInput ? searchInput.value.trim() : '';
        const perPage = perPageSelect ? perPageSelect.value : 10;

        const url = new URL('/sales/dispatches', window.location.origin);
        if (query) url.searchParams.set('q', query);
        url.searchParams.set('per_page', perPage);
        if (page > 1) url.searchParams.set('page', page);
        url.searchParams.set('ajax', '1');

        // Update browser URL without reloading page
        const displayUrl = new URL('/sales/dispatches', window.location.origin);
        if (query) displayUrl.searchParams.set('q', query);
        displayUrl.searchParams.set('per_page', perPage);
        if (page > 1) displayUrl.searchParams.set('page', page);
        window.history.replaceState({}, '', displayUrl.toString());

        if (tableBody) tableBody.style.opacity = '0.5';

        try {
            const res = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                signal: currentAbortController.signal
            });
            if (!res.ok) throw new Error('Search failed');
            const data = await res.json();

            if (tableBody) {
                tableBody.innerHTML = data.rows_html;
                tableBody.style.opacity = '1';
            }
            if (paginationContainer) {
                paginationContainer.innerHTML = data.pagination_html;
            }

            bindViewButtons();
            bindPaginationLinks();
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error(err);
                if (tableBody) tableBody.style.opacity = '1';
            }
        }
    }

    // Attach row view button handlers and pagination links initially
    bindViewButtons();
    bindPaginationLinks();

    closeBtn.addEventListener('click', closeModal);
    xBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal();
    });
    saveBtn.addEventListener('click', saveDispatch);

    // Per-page entries change
    if (perPageSelect) {
        perPageSelect.addEventListener('change', () => {
            fetchDispatches(1);
        });
    }

    // Live search input
    if (searchInput) {
        // Trigger live search on input with 180ms debounce
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchDispatches(1);
            }, 180);
        });

        // Immediate search on Enter key
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                fetchDispatches(1);
            }
        });
    }
})();
</script>
@endsection
