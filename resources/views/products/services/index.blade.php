@extends('layouts.app')

@section('title', 'Services - Products and Services')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/services.css') }}?v={{ file_exists(public_path('css/services.css')) ? filemtime(public_path('css/services.css')) : time() }}">
@endpush

@section('content')
<link rel="stylesheet" href="{{ asset('css/services.css') }}?v={{ filemtime(public_path('css/services.css')) }}">
<div class="services-container">

    <!-- Flash message alerts -->
    @if(session('success'))
    <div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg flex items-center justify-between">
        <span class="text-xs font-semibold"><i class="bi bi-check-circle-fill mr-1.5 text-emerald-600"></i>{{ session('success') }}</span>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="bi bi-x"></i></button>
    </div>
    @endif

    <!-- Header Row -->
    <div class="services-header-row">
        <div class="services-title-wrap">
            <h1 class="services-title">Services</h1>
            <span class="services-subtitle">Manage your service offerings & consumables</span>
        </div>
        <div class="flex items-center gap-3">
            <div class="services-breadcrumbs">
                <span>Products and Services</span>
                <i class="bi bi-chevron-right text-[10px]"></i>
                <span class="active">Services</span>
            </div>
            <button type="button" class="btn-add-service" id="btn-open-create-service">
                <i class="bi bi-plus-lg"></i> Add New
            </button>
        </div>
    </div>

    <!-- Card Wrapper -->
    <div class="services-card">

        <!-- Controls: Entries and Search -->
        <div class="services-table-controls">
            <form method="GET" action="{{ route('products.services.index') }}" id="entries-form" class="services-entries-wrap">
                <input type="hidden" name="search" value="{{ $search }}">
                <span>Show</span>
                <select name="per_page" class="services-entries-select" onchange="this.form.submit()">
                    <option value="10" @selected($perPage == 10)>10</option>
                    <option value="25" @selected($perPage == 25)>25</option>
                    <option value="50" @selected($perPage == 50)>50</option>
                    <option value="100" @selected($perPage == 100)>100</option>
                </select>
                <span>entries</span>
            </form>

            <form method="GET" action="{{ route('products.services.index') }}" class="services-search-wrap">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search services..." class="services-search-input" id="services-search-input">
                <i class="bi bi-search services-search-icon"></i>
            </form>
        </div>

        <!-- Services Table -->
        <div class="services-table-wrap">
            <table class="services-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Service Code <span class="sort-icon">⇅</span></th>
                        <th>Service Name <span class="sort-icon">⇅</span></th>
                        <th>Description <span class="sort-icon">⇅</span></th>
                        <th style="width: 90px;">Unit <span class="sort-icon">⇅</span></th>
                        <th style="width: 140px;">Unit Rate <span class="sort-icon">⇅</span></th>
                        <th style="width: 140px;">Unit Cost <span class="sort-icon">⇅</span></th>
                        <th style="width: 170px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                    <tr data-service-id="{{ $service->id }}">
                        <td class="col-id">{{ $service->id }}</td>
                        <td class="col-code">{{ $service->code }}</td>
                        <td class="col-name">{{ $service->name }}</td>
                        <td class="col-desc" title="{{ $service->description }}">{{ $service->description ?: '—' }}</td>
                        <td class="col-unit">{{ $service->display_unit }}</td>
                        <td class="col-rate">LKR {{ number_format($service->unit_rate, 2) }}</td>
                        <td class="col-cost">LKR {{ number_format($service->unit_cost, 2) }}</td>
                        <td>
                            <div class="services-action-cell">
                                <button type="button" class="btn-service-view" data-service-id="{{ $service->id }}">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button type="button"
                                        class="badge-service-status {{ $service->is_active ? 'is-active' : 'is-inactive' }}"
                                        data-service-id="{{ $service->id }}"
                                        title="Click to toggle status">
                                    <span class="inline-block w-1.5 h-1.5 rounded-full {{ $service->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                    {{ $service->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8">
                            <div class="services-empty-state">
                                <div class="services-empty-icon">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div class="services-empty-title">No services found</div>
                                <div class="services-empty-subtitle">
                                    {{ $search ? "No services matched your search term '{$search}'." : "You haven't added any services yet. Click 'Add New' to create your first service." }}
                                </div>
                                @if(!$search)
                                <button type="button" class="btn-add-service" onclick="document.getElementById('btn-open-create-service').click()">
                                    <i class="bi bi-plus-lg"></i> Add First Service
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="services-pagination-row">
            <div>
                Showing {{ $services->firstItem() ?? 0 }} to {{ $services->lastItem() ?? 0 }} of {{ $services->total() }} entries
            </div>
            <div>
                {{ $services->links('pagination::bootstrap-4') }}
            </div>
        </div>

    </div>
</div>

<!-- ==========================================================================
     New Service Modal (Photo 4 & 5)
     ========================================================================== -->
<div class="service-modal-backdrop" id="create-service-modal" aria-hidden="true">
    <div class="service-modal-card">
        <!-- Header -->
        <div class="service-modal-head">
            <h2 class="service-modal-title">New Service</h2>
            <button type="button" class="service-modal-close" data-close-modal>&times;</button>
        </div>

        <!-- Form -->
        <form id="create-service-form" method="POST" action="{{ route('products.services.store') }}" novalidate style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            @csrf
            <div class="service-modal-body">
                <div class="modal-form-grid">
                    <!-- Row 1: Code & Name -->
                    <div class="modal-form-row-2">
                        <div class="modal-form-group">
                            <label for="srv-code">Service Code</label>
                            <input type="text" name="code" id="srv-code" class="modal-input" placeholder="Service Code" required>
                        </div>
                        <div class="modal-form-group">
                            <label for="srv-name">Service Name</label>
                            <input type="text" name="name" id="srv-name" class="modal-input" placeholder="Service Name" required>
                        </div>
                    </div>

                    <!-- Row 2: Description -->
                    <div class="modal-form-group">
                        <label for="srv-desc">Description</label>
                        <input type="text" name="description" id="srv-desc" class="modal-input" placeholder="Service Description">
                    </div>

                    <!-- Row 3: Unit, Cost, Rate -->
                    <div class="modal-form-row-3">
                        <div class="modal-form-group">
                            <label for="srv-unit">Unit</label>
                            <select name="unit_id" id="srv-unit" class="modal-select">
                                <option value="">Service Unit</option>
                                @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->short_name }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="srv-cost">Unit Cost</label>
                            <input type="number" step="0.01" name="unit_cost" id="srv-cost" class="modal-input" placeholder="LKR 0.00">
                        </div>
                        <div class="modal-form-group">
                            <label for="srv-rate">Unit Rate</label>
                            <input type="number" step="0.01" name="unit_rate" id="srv-rate" class="modal-input" placeholder="LKR 0.00" required>
                        </div>
                    </div>

                    <!-- Row 4: Cost Ledger & Revenue Ledger -->
                    <div class="modal-form-row-2">
                        <div class="modal-form-group">
                            <label for="srv-cost-ledger">Cost Ledger</label>
                            <select name="cost_ledger_id" id="srv-cost-ledger" class="modal-select">
                                <option value="">Select...</option>
                                @foreach($paymentAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->account_number }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="modal-form-group">
                            <label for="srv-rev-ledger">Revenue Ledger</label>
                            <select name="revenue_ledger_id" id="srv-rev-ledger" class="modal-select">
                                <option value="">Select...</option>
                                @foreach($paymentAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->account_number }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Section: Service Consumables (Photo 4 & 5) -->
                    <div class="service-consumables-section">
                        <div class="consumables-ribbon-wrap">
                            <div class="consumables-header-tab">
                                Service Consumables
                            </div>
                        </div>
                        <div class="consumables-add-controls">
                            <div class="modal-form-group consumables-item-col">
                                <label for="consumable-product-select">Select Item</label>
                                <select id="consumable-product-select" class="modal-select">
                                    <option value="">Select...</option>
                                    @foreach($stockProducts as $prod)
                                    <option value="{{ $prod->id }}" data-name="{{ $prod->name }}" data-code="{{ $prod->code }}">{{ $prod->name }} ({{ $prod->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="modal-form-group consumables-qty-col">
                                <label for="consumable-qty-input">Quantity</label>
                                <input type="number" step="any" min="0" id="consumable-qty-input" class="modal-input" value="0">
                            </div>
                            <div class="consumables-btn-col">
                                <button type="button" class="btn-add-consumable" id="btn-add-consumable-row">
                                    + Add
                                </button>
                            </div>
                        </div>

                        <!-- Consumables Table -->
                        <div class="consumables-table-wrap">
                            <table class="consumables-table" id="consumables-table">
                                <thead>
                                    <tr>
                                        <th class="th-item">Item</th>
                                        <th class="th-qty">Qty</th>
                                    </tr>
                                </thead>
                                <tbody id="consumables-tbody">
                                    <tr class="consumables-empty-row">
                                        <td colspan="2" class="consumables-empty">No data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons (Photo 5) -->
            <div class="service-modal-footer">
                <button type="submit" class="btn-modal-save" name="action_type" value="save">Save</button>
                <button type="submit" class="btn-modal-save-new" name="action_type" value="save_and_new">Save and New</button>
                <button type="button" class="btn-modal-close" data-close-modal>Close</button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     View Service Modal
     ========================================================================== -->
<div class="service-modal-backdrop" id="view-service-modal" aria-hidden="true">
    <div class="service-modal-card">
        <div class="service-modal-head">
            <h2 class="service-modal-title" id="view-srv-title">Service Details</h2>
            <button type="button" class="service-modal-close" data-close-modal>&times;</button>
        </div>
        <div class="service-modal-body">
            <dl class="service-detail-grid">
                <div class="service-detail-card">
                    <dt>Service Code</dt>
                    <dd id="view-srv-code">—</dd>
                </div>
                <div class="service-detail-card">
                    <dt>Service Name</dt>
                    <dd id="view-srv-name">—</dd>
                </div>
                <div class="service-detail-card">
                    <dt>Unit</dt>
                    <dd id="view-srv-unit">—</dd>
                </div>
                <div class="service-detail-card">
                    <dt>Unit Rate</dt>
                    <dd id="view-srv-rate" class="text-emerald-700 font-bold">—</dd>
                </div>
                <div class="service-detail-card">
                    <dt>Unit Cost</dt>
                    <dd id="view-srv-cost">—</dd>
                </div>
                <div class="service-detail-card">
                    <dt>Status</dt>
                    <dd id="view-srv-status">—</dd>
                </div>
                <div class="service-detail-card" style="grid-column: 1 / -1;">
                    <dt>Description</dt>
                    <dd id="view-srv-desc">—</dd>
                </div>
                <div class="service-detail-card">
                    <dt>Cost Ledger</dt>
                    <dd id="view-srv-cost-ledger">—</dd>
                </div>
                <div class="service-detail-card">
                    <dt>Revenue Ledger</dt>
                    <dd id="view-srv-rev-ledger">—</dd>
                </div>
            </dl>

            <div class="service-consumables-section">
                <div class="consumables-header-tab">
                    Consumables Required
                </div>
                <div class="consumables-table-wrap">
                    <table class="consumables-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th style="width: 120px;">Quantity</th>
                            </tr>
                        </thead>
                        <tbody id="view-srv-consumables-tbody">
                            <tr><td colspan="2" class="consumables-empty">No consumables required</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="service-modal-footer">
            <button type="button" class="btn-modal-close" data-close-modal>Close</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    // Modal management
    const createModal = document.getElementById('create-service-modal');
    const viewModal = document.getElementById('view-service-modal');
    const openCreateBtn = document.getElementById('btn-open-create-service');
    const createForm = document.getElementById('create-service-form');

    function openModal(el) {
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
    }

    function closeModal(el) {
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
    }

    openCreateBtn?.addEventListener('click', () => {
        createForm.reset();
        document.getElementById('consumables-tbody').innerHTML = '<tr class="consumables-empty-row"><td colspan="3" class="consumables-empty">No data</td></tr>';
        openModal(createModal);
        document.getElementById('srv-code').focus();
    });

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            closeModal(createModal);
            closeModal(viewModal);
        });
    });

    // Close on backdrop click
    [createModal, viewModal].forEach(modal => {
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });

    // Close on Esc key
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal(createModal);
            closeModal(viewModal);
        }
    });

    // Consumables item addition
    const prodSelect = document.getElementById('consumable-product-select');
    const qtyInput = document.getElementById('consumable-qty-input');
    const addConsumableBtn = document.getElementById('btn-add-consumable-row');
    const tbody = document.getElementById('consumables-tbody');

    let consumableIndex = 0;

    addConsumableBtn?.addEventListener('click', () => {
        const prodId = prodSelect.value;
        const qty = parseFloat(qtyInput.value);

        if (!prodId) {
            alert('Please select an item');
            prodSelect.focus();
            return;
        }
        if (!qty || qty <= 0) {
            alert('Please enter a valid quantity greater than 0');
            qtyInput.focus();
            return;
        }

        const opt = prodSelect.options[prodSelect.selectedIndex];
        const prodName = opt.getAttribute('data-name') || opt.text;

        // Remove empty state
        const emptyRow = tbody.querySelector('.consumables-empty-row');
        if (emptyRow) emptyRow.remove();

        // Check if item already exists
        const existingInput = tbody.querySelector(`input[name$="[product_id]"][value="${prodId}"]`);
        if (existingInput) {
            const row = existingInput.closest('tr');
            const rowQtyInput = row.querySelector('input[name$="[quantity]"]');
            const newQty = parseFloat(rowQtyInput.value) + qty;
            rowQtyInput.value = newQty;
            row.querySelector('.qty-text').textContent = newQty.toFixed(2);
        } else {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <span class="font-medium text-slate-800">${prodName}</span>
                    <input type="hidden" name="consumables[${consumableIndex}][product_id]" value="${prodId}">
                </td>
                <td>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span class="qty-text font-semibold text-slate-700">${qty.toFixed(2)}</span>
                        <input type="hidden" name="consumables[${consumableIndex}][quantity]" value="${qty}">
                        <button type="button" class="btn-remove-consumable" title="Remove"><i class="bi bi-x-lg"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
            consumableIndex++;
        }

        // Reset inputs
        prodSelect.value = '';
        qtyInput.value = '0';
        prodSelect.focus();
    });

    tbody?.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.btn-remove-consumable');
        if (removeBtn) {
            removeBtn.closest('tr').remove();
            if (tbody.children.length === 0) {
                tbody.innerHTML = '<tr class="consumables-empty-row"><td colspan="2" class="consumables-empty">No data</td></tr>';
            }
        }
    });

    // Form submission with AJAX to handle Save vs Save and New
    createForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitter = e.submitter;
        const actionType = submitter ? submitter.value : 'save';

        const formData = new FormData(createForm);

        try {
            const response = await fetch(createForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: formData
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                alert(data.message || 'Error creating service. Please check fields.');
                return;
            }

            if (actionType === 'save_and_new') {
                createForm.reset();
                tbody.innerHTML = '<tr class="consumables-empty-row"><td colspan="3" class="consumables-empty">No data</td></tr>';
                document.getElementById('srv-code').focus();
            } else {
                closeModal(createModal);
                window.location.reload();
            }
        } catch (err) {
            console.error(err);
            createForm.submit(); // fallback to normal post
        }
    });

    // Delegated actions for status toggle and view details
    document.addEventListener('click', async (e) => {
        const badge = e.target.closest('.badge-service-status');
        if (badge && badge.dataset.serviceId) {
            e.preventDefault();
            const id = badge.dataset.serviceId;
            badge.disabled = true;
            try {
                const res = await fetch(`/products/services/${id}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
                const data = await res.json();
                if (data.success) {
                    if (data.is_active) {
                        badge.className = 'badge-service-status is-active';
                        badge.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active';
                    } else {
                        badge.className = 'badge-service-status is-inactive';
                        badge.innerHTML = '<span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inactive';
                    }
                }
            } catch (err) {
                console.error('Failed to toggle status:', err);
            } finally {
                badge.disabled = false;
            }
            return;
        }

        const viewBtn = e.target.closest('.btn-service-view');
        if (viewBtn && viewBtn.dataset.serviceId) {
            e.preventDefault();
            const id = viewBtn.dataset.serviceId;
            try {
                const res = await fetch(`/products/services/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.service) {
                    const s = data.service;
                    document.getElementById('view-srv-title').textContent = `${s.name} (${s.code})`;
                    document.getElementById('view-srv-code').textContent = s.code;
                    document.getElementById('view-srv-name').textContent = s.name;
                    document.getElementById('view-srv-unit').textContent = s.unit ? (s.unit.short_name || s.unit.name) : '—';
                    document.getElementById('view-srv-rate').textContent = 'LKR ' + Number(s.unit_rate).toLocaleString(undefined, {minimumFractionDigits: 2});
                    document.getElementById('view-srv-cost').textContent = 'LKR ' + Number(s.unit_cost).toLocaleString(undefined, {minimumFractionDigits: 2});
                    document.getElementById('view-srv-status').innerHTML = s.is_active
                        ? '<span class="badge-service-status is-active" style="cursor:default;"><span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active</span>'
                        : '<span class="badge-service-status is-inactive" style="cursor:default;"><span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inactive</span>';
                    document.getElementById('view-srv-desc').textContent = s.description || '—';
                    document.getElementById('view-srv-cost-ledger').textContent = s.cost_ledger ? s.cost_ledger.name : '—';
                    document.getElementById('view-srv-rev-ledger').textContent = s.revenue_ledger ? s.revenue_ledger.name : '—';

                    const vTbody = document.getElementById('view-srv-consumables-tbody');
                    vTbody.replaceChildren();
                    if (s.consumables && s.consumables.length > 0) {
                        s.consumables.forEach(c => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${c.product ? c.product.name + ' (' + c.product.code + ')' : 'Product #' + c.product_id}</td>
                                <td class="font-bold">${Number(c.quantity).toFixed(2)}</td>
                            `;
                            vTbody.appendChild(tr);
                        });
                    } else {
                        vTbody.innerHTML = '<tr><td colspan="2" class="consumables-empty">No consumables required</td></tr>';
                    }

                    openModal(viewModal);
                }
            } catch (err) {
                console.error('Failed to load service details:', err);
            }
        }
    });
})();
</script>
@endpush
@endsection
