(() => {
    function initialize() {
        const shell = document.querySelector('.odoo-invoice-shell');
        if (!shell || shell.dataset.referenceReady) return;
        shell.dataset.referenceReady = '1';

        const header = shell.querySelector('.odoo-header-grid');
        if (header) {
            ['customer_reference', 'salesperson_id', 'location_id', 'commission_agent_id'].forEach(name => {
                const field = shell.querySelector(`[name="${name}"]`)?.closest('.odoo-field');
                if (field) header.append(field);
            });
            const positions = {
                customer_id: 'customer', journal: 'type', customer_reference: 'reference',
                invoice_date: 'date', due_date: 'due', salesperson_id: 'salesperson',
                location_id: 'location', commission_agent_id: 'agent'
            };
            Object.entries(positions).forEach(([name, position]) => {
                header.querySelector(`[name="${name}"]`)?.closest('.odoo-field')?.classList.add(`reference-field-${position}`);
            });
        }

        const add = shell.querySelector('[data-add="product"]');
        if (add) add.textContent = 'Add Item';
        const note = shell.querySelector('[data-add="note"]');
        if (note) note.textContent = 'Add Text';
        const section = shell.querySelector('[data-add="section"]');
        if (section) section.hidden = true;
        const catalogButton = document.getElementById('open-catalog');
        if (catalogButton) {
            catalogButton.textContent = 'Browse Products';
            catalogButton.addEventListener('click', (e) => {
                e.preventDefault();
                const catalog = document.getElementById('catalog-workspace');
                if (catalog) catalog.classList.remove('hidden');
            });
        }

        const service = document.createElement('button');
        service.type = 'button';
        service.textContent = 'Add Service';
        service.disabled = shell.hasAttribute('data-invoice-locked');
        if (add) add.after(service);

        // Keep the table's horizontal scrolling separate from the notes and totals.
        const tableHead = shell.querySelector('.odoo-line-head');
        const lines = document.getElementById('invoice-lines');
        const tableScroll = document.createElement('div');
        tableScroll.className = 'reference-lines-scroll';
        tableHead.before(tableScroll);

        const tableInner = document.createElement('div');
        tableInner.className = 'reference-table-inner';
        tableScroll.appendChild(tableInner);
        tableInner.append(tableHead, lines);

        tableHead.replaceChildren(...['Type', 'Code', 'Details', 'Sales Price', 'Discount (%)', 'Discount Amt.', 'Available Qty', 'Qty', 'Total', 'Action'].map(text => {
            const label = document.createElement('span');
            label.textContent = text;
            return label;
        }));
        const products = JSON.parse(document.getElementById('reference-product-metadata')?.textContent || '{}');
        const lots = JSON.parse(document.getElementById('reference-lot-metadata')?.textContent || '{}');
        const serialsMetadata = JSON.parse(document.getElementById('reference-serial-metadata')?.textContent || '{}');
        const servicesMetadata = JSON.parse(document.getElementById('reference-service-metadata')?.textContent || '[]');

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        let allProductOptions = null;
        function getAllOptions(currentSelect) {
            if (!allProductOptions || allProductOptions.length <= 1) {
                const tpl = document.getElementById('invoice-line-template');
                const sel = tpl?.content.querySelector('.line-product') || currentSelect || lines?.querySelector('.line-product');
                if (sel && sel.options.length > 1) {
                    allProductOptions = [...sel.options].map(opt => ({
                        value: opt.value,
                        text: opt.textContent,
                        price: opt.dataset.price || '0',
                        tax: opt.dataset.tax || ''
                    }));
                }
            }
            return allProductOptions || [];
        }

        function populateProductSelect(select, targetType) {
            if (!select) return;
            const all = getAllOptions(select);
            const curVal = select.value;
            select.replaceChildren();

            all.forEach(opt => {
                if (!opt.value) {
                    const el = document.createElement('option');
                    el.value = '';
                    el.textContent = 'Select...';
                    select.appendChild(el);
                    return;
                }

                const isStock = products[opt.value]?.stock !== false;
                if (targetType === 'service' && isStock) return;
                if (targetType === 'item' && !isStock) return;

                const el = document.createElement('option');
                el.value = opt.value;
                el.textContent = opt.text;
                el.dataset.price = opt.price;
                el.dataset.tax = opt.tax;
                select.appendChild(el);
            });

            if (curVal && [...select.options].some(o => o.value === curVal)) {
                select.value = curVal;
            } else {
                select.value = '';
            }
        }

        function isRowEmpty(row) {
            if (!row) return false;
            if (row.classList.contains('odoo-line')) {
                const prod = row.querySelector('.line-product');
                const srv = row.querySelector('[data-name="service_id"]');
                const srvText = row.querySelector('.service-search-input')?.value || row.querySelector('.line-description')?.value;
                const price = parseFloat(row.querySelector('.line-price')?.value || 0) || 0;
                const disc = parseFloat(row.querySelector('.line-discount')?.value || 0) || 0;
                const hasProduct = prod && prod.value !== '';
                const hasService = (srv && srv.value !== '') || (srvText && srvText.trim() !== '');
                return !hasProduct && !hasService && price === 0 && disc === 0;
            }
            if (row.classList.contains('odoo-text-line')) {
                const desc = row.querySelector('[data-name="description"]') || row.querySelector('.line-description');
                return !desc || desc.value.trim() === '';
            }
            return false;
        }

        function getRowType(row) {
            if (!row) return null;
            if (row.classList.contains('odoo-text-line') || row.dataset.kind === 'note' || row.dataset.kind === 'section') return 'text';
            if (row.classList.contains('odoo-line')) {
                if (row.dataset.targetKind === 'service' || row.dataset.kind === 'service' || row.querySelector('[data-name="line_type"]')?.value === 'service') return 'service';
                if (row.dataset.targetKind === 'item') return 'item';
                const prod = row.querySelector('.line-product')?.value;
                if (prod && products[prod] && !products[prod].stock) return 'service';
                return 'item';
            }
            return null;
        }

        function focusRow(row, type) {
            if (!row) return;
            const scrollContainer = shell.querySelector('.reference-lines-scroll');
            if (scrollContainer) scrollContainer.scrollLeft = 0;
            if (type === 'item') {
                const sel = row.querySelector('.line-product');
                if (sel) sel.focus({ preventScroll: true });
            } else if (type === 'service') {
                const btn = row.querySelector('.service-placeholder-btn');
                if (btn && !btn.classList.contains('hidden')) {
                    btn.focus({ preventScroll: true });
                }
            } else if (type === 'text') {
                const inp = row.querySelector('[data-name="description"]') || row.querySelector('.line-description');
                if (inp) inp.focus({ preventScroll: true });
            }
        }

        function setupServiceAutocomplete(row, serviceBox) {
            if (serviceBox.dataset.eventsBound) return;
            serviceBox.dataset.eventsBound = '1';

            const placeholderBtn = serviceBox.querySelector('.service-placeholder-btn');
            const inputWrap = serviceBox.querySelector('.service-input-wrap');
            const input = serviceBox.querySelector('.service-search-input');
            const arrow = serviceBox.querySelector('.service-dropdown-arrow');
            const list = serviceBox.querySelector('.service-dropdown-list');
            const detailsCell = row.querySelector('.line-details-cell');
            const confirmed = detailsCell.querySelector('.line-details-confirmed');
            const titleEl = confirmed?.querySelector('.line-details-title');
            const changeBtn = confirmed?.querySelector('.btn-change-item');
            const infoBtn = confirmed?.querySelector('.btn-item-info');
            const srvInput = row.querySelector('[data-name="service_id"]');
            const descInput = row.querySelector('.line-description');
            const priceInput = row.querySelector('.line-price');
            const badge = row.querySelector('.reference-line-type');
            const codeEl = row.querySelector('.reference-line-code');

            if (infoBtn) infoBtn.style.display = 'none';

            function renderList(query = '') {
                const q = (query || '').trim().toLowerCase();
                const matches = (servicesMetadata || []).filter(s => {
                    if (!q) return true;
                    return (s.name && s.name.toLowerCase().includes(q)) || 
                           (s.code && s.code.toLowerCase().includes(q)) ||
                           (s.description && s.description.toLowerCase().includes(q));
                });

                list.replaceChildren();

                if (matches.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'service-dropdown-empty';
                    empty.innerHTML = `<span>No registered services matching "<b>${escapeHtml(query)}</b>"</span><small>Press Enter to use as custom service</small>`;
                    list.appendChild(empty);
                } else {
                    matches.forEach(s => {
                        const item = document.createElement('div');
                        item.className = 'service-dropdown-item';
                        item.dataset.id = s.id;
                        item.dataset.code = s.code;
                        item.dataset.name = s.name;
                        item.dataset.rate = s.unit_rate;
                        item.innerHTML = `
                            <div class="service-item-main">
                                <span class="service-item-code">${escapeHtml(s.code)}</span>
                                <strong class="service-item-name">${escapeHtml(s.name)}</strong>
                            </div>
                            <span class="service-item-price">LKR ${Number(s.unit_rate).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        `;
                        item.addEventListener('mousedown', (e) => {
                            e.preventDefault();
                            selectService(s);
                        });
                        list.appendChild(item);
                    });
                }
                list.classList.remove('hidden');
                const linesContainer = document.getElementById('invoice-lines');
                if (linesContainer) linesContainer.style.paddingBottom = '180px';
            }

            function closeList() {
                list.classList.add('hidden');
                const linesContainer = document.getElementById('invoice-lines');
                if (linesContainer) linesContainer.style.paddingBottom = '';
            }

            function openSearchMode() {
                if (placeholderBtn) placeholderBtn.classList.add('hidden');
                if (inputWrap) {
                    inputWrap.classList.remove('hidden');
                    inputWrap.classList.add('active-editing');
                }
                if (confirmed) confirmed.classList.add('hidden');
                input.value = '';
                input.focus();
                renderList('');
            }

            function selectService(s) {
                if (srvInput) srvInput.value = s.id;
                const displayName = `${s.code} | ${s.name}`;
                input.value = displayName;
                if (descInput) descInput.value = displayName;
                if (priceInput) {
                    priceInput.value = Number(s.unit_rate || 0).toFixed(2);
                    priceInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (badge) {
                    badge.textContent = 'Service';
                    badge.style.background = '#e0f2fe';
                    badge.style.color = '#0284c7';
                }
                if (codeEl) codeEl.textContent = s.code;

                if (titleEl) titleEl.textContent = displayName;
                serviceBox.classList.add('hidden');
                if (inputWrap) {
                    inputWrap.classList.add('hidden');
                    inputWrap.classList.remove('active-editing');
                }
                if (placeholderBtn) placeholderBtn.classList.add('hidden');
                closeList();
                if (confirmed) confirmed.classList.remove('hidden');
                row.classList.remove('row-pending');
                row.dataset.rowStatus = 'confirmed';

                decorateRows();
                if (typeof window.invoiceCalc === 'function') window.invoiceCalc();
                if (typeof updateSummaryTotals === 'function') updateSummaryTotals();
            }

            function confirmCustomService(val) {
                const text = val.trim();
                if (!text) return;
                if (srvInput) srvInput.value = '';
                input.value = text;
                if (descInput) descInput.value = text;
                if (badge) {
                    badge.textContent = 'Service';
                    badge.style.background = '#e0f2fe';
                    badge.style.color = '#0284c7';
                }
                if (codeEl) codeEl.textContent = 'SRV';
                if (titleEl) titleEl.textContent = text;
                serviceBox.classList.add('hidden');
                if (inputWrap) {
                    inputWrap.classList.add('hidden');
                    inputWrap.classList.remove('active-editing');
                }
                if (placeholderBtn) placeholderBtn.classList.add('hidden');
                closeList();
                if (confirmed) confirmed.classList.remove('hidden');
                row.classList.remove('row-pending');
                row.dataset.rowStatus = 'confirmed';

                decorateRows();
                if (typeof window.invoiceCalc === 'function') window.invoiceCalc();
                if (typeof updateSummaryTotals === 'function') updateSummaryTotals();
            }

            // Clicking the resting field in the table row opens search & dropdown
            placeholderBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openSearchMode();
            });
            placeholderBtn?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openSearchMode();
                }
            });

            input.addEventListener('input', () => {
                renderList(input.value);
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const firstItem = list.querySelector('.service-dropdown-item');
                    if (firstItem && !list.classList.contains('hidden') && list.querySelectorAll('.service-dropdown-item').length === 1) {
                        const s = {
                            id: firstItem.dataset.id,
                            code: firstItem.dataset.code,
                            name: firstItem.dataset.name,
                            unit_rate: firstItem.dataset.rate
                        };
                        selectService(s);
                    } else if (input.value.trim()) {
                        confirmCustomService(input.value);
                    }
                } else if (e.key === 'Escape') {
                    closeList();
                    if (!srvInput?.value && (!descInput?.value || !descInput.value.trim())) {
                        if (inputWrap) {
                            inputWrap.classList.add('hidden');
                            inputWrap.classList.remove('active-editing');
                        }
                        if (placeholderBtn) placeholderBtn.classList.remove('hidden');
                    }
                }
            });

            input.addEventListener('blur', () => {
                setTimeout(() => {
                    closeList();
                    if (row.dataset.targetKind === 'service') {
                        if (input.value.trim() && !srvInput?.value && row.classList.contains('row-pending')) {
                            confirmCustomService(input.value);
                        } else if (!srvInput?.value && (!descInput?.value || !descInput.value.trim())) {
                            if (inputWrap) {
                                inputWrap.classList.add('hidden');
                                inputWrap.classList.remove('active-editing');
                            }
                            if (placeholderBtn) placeholderBtn.classList.remove('hidden');
                        }
                    }
                }, 200);
            });

            arrow.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (list.classList.contains('hidden')) {
                    renderList(input.value);
                    input.focus();
                } else {
                    closeList();
                }
            });

            // Item is confirmed permanently; do not allow clicking to edit
        }

        function configureRow(row, type) {
            if (!row || !row.classList.contains('odoo-line')) return;
            row.dataset.targetKind = type;
            row.dataset.kind = type === 'service' ? 'service' : 'product';

            const lineTypeInput = row.querySelector('[data-name="line_type"]');
            if (lineTypeInput) lineTypeInput.value = type === 'service' ? 'service' : 'product';

            let badge = row.querySelector('.reference-line-type');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'reference-line-type';
                row.prepend(badge);
            }
            let codeEl = row.querySelector('.reference-line-code');
            if (!codeEl) {
                codeEl = document.createElement('span');
                codeEl.className = 'reference-line-code';
                badge.after(codeEl);
            }

            const select = row.querySelector('.line-product');
            const detailsCell = row.querySelector('.line-details-cell');
            if (!detailsCell) return;

            // Ensure hidden service_id input exists
            let srvInput = row.querySelector('[data-name="service_id"]');
            if (!srvInput) {
                srvInput = document.createElement('input');
                srvInput.type = 'hidden';
                srvInput.dataset.name = 'service_id';
                srvInput.className = 'line-service-id';
                if (lineTypeInput && lineTypeInput.name) {
                    srvInput.name = lineTypeInput.name.replace('[line_type]', '[service_id]');
                }
                row.appendChild(srvInput);
            }

            // Ensure service autocomplete box exists
            let serviceBox = detailsCell.querySelector('.service-autocomplete-box');
            if (!serviceBox) {
                serviceBox = document.createElement('div');
                serviceBox.className = 'service-autocomplete-box';
                serviceBox.innerHTML = `
                    <div class="service-placeholder-btn" role="button" tabindex="0" title="Click to select service">
                        <span class="service-placeholder-text">Select Service...</span>
                        <i class="bi bi-chevron-down service-placeholder-arrow"></i>
                    </div>
                    <div class="service-input-wrap hidden">
                        <i class="bi bi-gear-wide-connected service-icon"></i>
                        <input type="text" class="service-search-input" placeholder="Type to search service..." autocomplete="off">
                        <button type="button" class="service-dropdown-arrow" tabindex="-1" title="Show services"><i class="bi bi-chevron-down"></i></button>
                    </div>
                    <div class="service-dropdown-list hidden"></div>
                `;
                detailsCell.appendChild(serviceBox);
            }
            setupServiceAutocomplete(row, serviceBox);

            const lotPickerWrap = detailsCell.querySelector('.line-lot-picker-wrap');
            const confirmed = detailsCell.querySelector('.line-details-confirmed');
            const serialBtn = row.querySelector('.line-serial-btn');

            if (type === 'service') {
                badge.textContent = 'Service';
                badge.style.background = '#e0f2fe';
                badge.style.color = '#0284c7';

                if (select) {
                    populateProductSelect(select, type);
                    if (servicesMetadata && servicesMetadata.length > 0) {
                        select.classList.add('hidden');
                    } else {
                        select.classList.remove('hidden');
                    }
                    select.value = '';
                }
                if (lotPickerWrap) lotPickerWrap.classList.add('hidden');

                const curDesc = row.querySelector('.line-description')?.value || '';
                const curSrvId = srvInput?.value;
                const curSrv = curSrvId ? (servicesMetadata || []).find(s => String(s.id) === String(curSrvId)) : null;

                const placeholderBtn = serviceBox.querySelector('.service-placeholder-btn');
                const inputWrap = serviceBox.querySelector('.service-input-wrap');
                const list = serviceBox.querySelector('.service-dropdown-list');

                if (curSrv || (curDesc && curDesc.trim())) {
                    codeEl.textContent = curSrv?.code || 'SRV';
                    const titleEl = confirmed?.querySelector('.line-details-title');
                    if (titleEl) titleEl.textContent = curSrv ? `${curSrv.code} | ${curSrv.name}` : curDesc;
                    if (confirmed) confirmed.classList.remove('hidden');
                    serviceBox.classList.add('hidden');
                    row.classList.remove('row-pending');
                    row.dataset.rowStatus = 'confirmed';
                } else {
                    codeEl.textContent = '—';
                    if (confirmed) confirmed.classList.add('hidden');
                    serviceBox.classList.remove('hidden');
                    if (placeholderBtn) placeholderBtn.classList.remove('hidden');
                    if (inputWrap) {
                        inputWrap.classList.add('hidden');
                        inputWrap.classList.remove('active-editing');
                    }
                    if (list) list.classList.add('hidden');
                    row.classList.add('row-pending');
                    row.dataset.rowStatus = 'pending';
                }

                const availEl = row.querySelector('.reference-line-available');
                if (availEl) availEl.textContent = '—';
                const lotSelect = row.querySelector('.line-lot');
                if (lotSelect) {
                    lotSelect.disabled = true;
                    lotSelect.replaceChildren();
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No available lots';
                    lotSelect.appendChild(opt);
                }
                if (serialBtn) serialBtn.style.visibility = 'hidden';
            } else {
                badge.textContent = 'Stock';
                badge.style.background = '#ede9fe';
                badge.style.color = '#6d28d9';

                serviceBox.classList.add('hidden');
                if (srvInput) srvInput.value = '';
                if (serialBtn) serialBtn.style.visibility = 'visible';

                populateProductSelect(select, type);
                const pVal = select?.value;
                const pMeta = products[pVal];
                codeEl.textContent = pMeta?.code || '—';
                if (pVal) {
                    if (confirmed) confirmed.classList.remove('hidden');
                    if (select) select.classList.add('hidden');
                } else {
                    if (select) select.classList.remove('hidden');
                    if (confirmed) confirmed.classList.add('hidden');
                }
            }
        }

        const locationSelect = shell.querySelector('[name="location_id"]');

        function openItemInfoModal(productId) {
            const meta = products[productId] || {};
            const pLots = lots[productId] || [];

            const popup = document.getElementById('product-info-popup');
            const backdrop = document.getElementById('product-info-backdrop');
            if (!popup) return;

            // Head
            const nameEl = document.getElementById('info-product-name');
            if (nameEl) nameEl.textContent = meta.name || 'Product Details';

            const skuEl = document.getElementById('info-product-code-sku');
            if (skuEl) skuEl.textContent = `${meta.code || '0000'} - ${meta.sku_key || meta.code || '12121212'}`;

            // Item Details
            const descEl = document.getElementById('info-desc');
            if (descEl) descEl.textContent = meta.name || '—';

            const barcodeEl = document.getElementById('info-barcode');
            if (barcodeEl) barcodeEl.textContent = meta.sku_key || meta.code || '12121212';

            const unitEl = document.getElementById('info-unit');
            if (unitEl) unitEl.textContent = meta.unit || 'Nos';

            const criticalEl = document.getElementById('info-critical');
            if (criticalEl) criticalEl.textContent = meta.alert_quantity ?? 1;

            // Type & Properties
            const typeBadge = document.getElementById('info-type-badge');
            if (typeBadge) typeBadge.textContent = meta.product_type || (meta.stock ? 'Stock Item' : 'Service Item');

            const propBadge = document.getElementById('info-prop-badge');
            if (propBadge) propBadge.textContent = meta.properties || 'Standard';

            // Ledgers
            const ledgerStock = document.getElementById('info-ledger-stock');
            if (ledgerStock) ledgerStock.textContent = meta.stock_ledger || 'Mashi Distributors Advance Creditor Ledger';

            const ledgerCogs = document.getElementById('info-ledger-cogs');
            if (ledgerCogs) ledgerCogs.textContent = meta.cogs_ledger || 'VAT Payable';

            const ledgerRevenue = document.getElementById('info-ledger-revenue');
            if (ledgerRevenue) ledgerRevenue.textContent = meta.revenue_ledger || 'Sales';

            // Available Lots Table
            const tbody = document.getElementById('info-lots-tbody');
            if (tbody) {
                tbody.replaceChildren();
                if (pLots.length > 0) {
                    pLots.forEach(lot => {
                        const tr = document.createElement('tr');
                        const cost = Number(lot.cost || 0);
                        const salePrice = Number(lot.price || meta.selling_price || 0);
                        const minPrice = cost > 0 ? (cost * 1.1) : (salePrice * 0.825);
                        const laPrice = salePrice * 1.25;

                        tr.innerHTML = `
                            <td>${lot.number || '1'}</td>
                            <td>${lot.batch || '01'}</td>
                            <td>${cost.toFixed(2)}</td>
                            <td>${salePrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            <td>${minPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            <td>${laPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            <td>${lot.available}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td colspan="7" style="text-align: center; color: #94a3b8; padding: 14px;">No available lots for this item</td>`;
                    tbody.appendChild(tr);
                }
            }

            popup.classList.add('open');
            if (backdrop) backdrop.classList.add('open');
        }

        const closeItemInfo = () => {
            document.getElementById('product-info-popup')?.classList.remove('open');
            document.getElementById('product-info-backdrop')?.classList.remove('open');
        };
        document.getElementById('btn-close-item-info')?.addEventListener('click', closeItemInfo);
        document.getElementById('product-info-backdrop')?.addEventListener('click', closeItemInfo);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeItemInfo();
        });

        // ==========================================================================
        // Duplicate Item Detection & Modal ("Already on this invoice" - Photo 1)
        // ==========================================================================
        let duplicateDialog = document.getElementById('duplicate-item-dialog');
        let currentDuplicateContext = null;

        function findExistingRow(productId, lotId, type, excludeRow = null) {
            if (!lines) return null;
            const allRows = lines.querySelectorAll('.odoo-line');
            for (const r of allRows) {
                if (r === excludeRow) continue;
                const pId = r.querySelector('.line-product')?.value;
                if (!pId || String(pId) !== String(productId)) continue;

                if (lotId) {
                    const lId = r.querySelector('.line-lot')?.value || r.querySelector('.line-lot-picker')?.value;
                    if (lId && String(lId) !== String(lotId)) continue;
                }

                const price = parseFloat(r.querySelector('.line-price')?.value || 0) || 0;
                const isFree = r.dataset.isFreeIssue === '1' || price === 0;

                if (type === 'paid' && !isFree) return r;
                if (type === 'free' && isFree) return r;
            }
            return null;
        }

        function showDuplicateModal(ctx) {
            currentDuplicateContext = ctx;
            if (!duplicateDialog) duplicateDialog = document.getElementById('duplicate-item-dialog');
            if (!duplicateDialog) return;

            const subtitleEl = document.getElementById('dup-item-subtitle');
            const qtyEl = document.getElementById('dup-item-qty');

            if (subtitleEl) {
                subtitleEl.textContent = `${ctx.productId} | ${ctx.productName} | ${ctx.lotNumber}`;
            }
            if (qtyEl) {
                qtyEl.textContent = ctx.qty || 1;
            }

            if (typeof duplicateDialog.showModal === 'function') {
                duplicateDialog.showModal();
            } else {
                duplicateDialog.setAttribute('open', '');
            }
        }
        window.showInvoiceDuplicateModal = showDuplicateModal;

        function confirmRowAsPaid(row, ctx) {
            row.dataset.isFreeIssue = '0';
            row.dataset.rowStatus = 'confirmed';
            row.classList.remove('row-pending');

            const pSel = row.querySelector('.line-product');
            if (pSel) {
                pSel.value = ctx.productId;
                pSel.classList.add('hidden');
            }
            const lotSel = row.querySelector('.line-lot');
            if (lotSel && ctx.lotId) {
                if (![...lotSel.options].some(o => o.value === String(ctx.lotId))) {
                    const opt = document.createElement('option');
                    opt.value = ctx.lotId;
                    opt.textContent = `Lot: ${ctx.lotNumber}`;
                    lotSel.appendChild(opt);
                }
                lotSel.value = ctx.lotId;
            }
            row.querySelector('.line-lot-picker-wrap')?.classList.add('hidden');

            const curProduct = products[ctx.productId];
            const unitPrice = ctx.lotPrice || curProduct?.selling_price || 0;
            const priceInp = row.querySelector('.line-price');
            if (priceInp) {
                priceInp.value = Number(unitPrice).toFixed(2);
            }
            const qtyInp = row.querySelector('.line-qty');
            if (qtyInp) {
                qtyInp.value = ctx.qty || 1;
            }
            const descInp = row.querySelector('.line-description');
            if (descInp) {
                descInp.value = curProduct?.name || ctx.productName || '';
            }

            const detailsCell = row.querySelector('.line-details-cell');
            const confirmed = detailsCell?.querySelector('.line-details-confirmed');
            const titleEl = confirmed?.querySelector('.line-details-title');
            if (confirmed) confirmed.classList.remove('hidden');
            if (titleEl) {
                titleEl.textContent = `${ctx.productId} | ${curProduct?.name || ctx.productName} | ${ctx.lotNumber || curProduct?.code || ''}`;
            }
            const codeEl = row.querySelector('.reference-line-code');
            if (codeEl) codeEl.textContent = ctx.lotNumber || curProduct?.code || '';
        }

        function configureRowAsFreeIssue(row, ctx) {
            row.dataset.isFreeIssue = '1';
            row.dataset.rowStatus = 'confirmed';
            row.classList.remove('row-pending');

            const pSel = row.querySelector('.line-product');
            if (pSel) {
                pSel.value = ctx.productId;
                pSel.classList.add('hidden');
            }
            const lotSel = row.querySelector('.line-lot');
            if (lotSel && ctx.lotId) {
                if (![...lotSel.options].some(o => o.value === String(ctx.lotId))) {
                    const opt = document.createElement('option');
                    opt.value = ctx.lotId;
                    opt.textContent = `Lot: ${ctx.lotNumber}`;
                    lotSel.appendChild(opt);
                }
                lotSel.value = ctx.lotId;
            }
            row.querySelector('.line-lot-picker-wrap')?.classList.add('hidden');

            const priceInp = row.querySelector('.line-price');
            if (priceInp) priceInp.value = '0.00';
            const discInp = row.querySelector('.line-discount');
            if (discInp) discInp.value = '0';
            const qtyInp = row.querySelector('.line-qty');
            if (qtyInp) qtyInp.value = ctx.qty || 1;

            const descInp = row.querySelector('.line-description');
            const curProduct = products[ctx.productId];
            const baseDesc = curProduct?.name || ctx.productName || '';
            if (descInp) {
                descInp.value = `${baseDesc} [Free Issue]`;
            }

            const detailsCell = row.querySelector('.line-details-cell');
            const confirmed = detailsCell?.querySelector('.line-details-confirmed');
            const titleEl = confirmed?.querySelector('.line-details-title');
            if (confirmed) confirmed.classList.remove('hidden');
            if (titleEl) {
                titleEl.innerHTML = `${escapeHtml(ctx.productId)} | ${escapeHtml(baseDesc)} | ${escapeHtml(ctx.lotNumber)} <span class="badge-free-issue">Free Issue</span>`;
            }
            const codeEl = row.querySelector('.reference-line-code');
            if (codeEl) codeEl.textContent = ctx.lotNumber || curProduct?.code || '';
        }

        const closeDuplicateDialog = () => {
            if (duplicateDialog) {
                if (typeof duplicateDialog.close === 'function') duplicateDialog.close();
                else duplicateDialog.removeAttribute('open');
            }
        };

        document.getElementById('btn-dup-paid')?.addEventListener('click', () => {
            if (!currentDuplicateContext) return;
            const ctx = currentDuplicateContext;
            closeDuplicateDialog();

            const existingPaid = findExistingRow(ctx.productId, ctx.lotId, 'paid', ctx.sourceRow);
            const qtyToAdd = ctx.qty || 1;

            if (existingPaid) {
                const qtyInp = existingPaid.querySelector('.line-qty');
                if (qtyInp) {
                    qtyInp.value = (parseFloat(qtyInp.value || 0) + qtyToAdd);
                    qtyInp.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (ctx.sourceRow) {
                    ctx.sourceRow.remove();
                }
            } else if (ctx.sourceRow) {
                confirmRowAsPaid(ctx.sourceRow, ctx);
            }

            if (typeof window.invoiceCalc === 'function') window.invoiceCalc();
            decorateRows();
            currentDuplicateContext = null;
        });

        document.getElementById('btn-dup-free')?.addEventListener('click', () => {
            if (!currentDuplicateContext) return;
            const ctx = currentDuplicateContext;
            closeDuplicateDialog();

            const existingFree = findExistingRow(ctx.productId, ctx.lotId, 'free', ctx.sourceRow);
            const qtyToAdd = ctx.qty || 1;

            if (existingFree) {
                const qtyInp = existingFree.querySelector('.line-qty');
                if (qtyInp) {
                    qtyInp.value = (parseFloat(qtyInp.value || 0) + qtyToAdd);
                    qtyInp.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (ctx.sourceRow) {
                    ctx.sourceRow.remove();
                }
            } else {
                let targetRow = ctx.sourceRow;
                if (!targetRow) {
                    targetRow = createItemOrServiceRow('product');
                }
                if (targetRow) {
                    configureRowAsFreeIssue(targetRow, ctx);
                }
            }

            if (typeof window.invoiceCalc === 'function') window.invoiceCalc();
            decorateRows();
            currentDuplicateContext = null;
        });

        document.getElementById('btn-dup-cancel')?.addEventListener('click', () => {
            if (currentDuplicateContext?.sourceRow) {
                const row = currentDuplicateContext.sourceRow;
                if (lines.children.length > 1) {
                    row.remove();
                } else {
                    const pSel = row.querySelector('.line-product');
                    if (pSel) pSel.value = '';
                    row.classList.add('row-pending');
                    row.querySelector('.line-lot-picker-wrap')?.classList.add('hidden');
                    row.querySelector('.line-details-confirmed')?.classList.add('hidden');
                }
            }
            closeDuplicateDialog();
            currentDuplicateContext = null;
        });

        duplicateDialog?.addEventListener('click', (e) => {
            if (e.target === duplicateDialog) {
                document.getElementById('btn-dup-cancel')?.click();
            }
        });

        function decorateRows() {
            lines.querySelectorAll('.odoo-line').forEach(row => {
                if (!row.querySelector('.reference-line-type')) {
                    const type = document.createElement('span');
                    type.className = 'reference-line-type';
                    const code = document.createElement('span');
                    code.className = 'reference-line-code';
                    row.prepend(type, code);
                }
                if (!row.querySelector('.reference-line-available')) {
                    const available = document.createElement('span');
                    available.className = 'reference-line-available';
                    row.append(available);
                }
                // Ensure action wrap with target circle & trash button exists (Photo 3)
                let actionsWrap = row.querySelector('.line-actions-wrap');
                if (!actionsWrap) {
                    const removeBtn = row.querySelector('.line-remove');
                    if (removeBtn) {
                        actionsWrap = document.createElement('div');
                        actionsWrap.className = 'line-actions-wrap';
                        removeBtn.before(actionsWrap);
                        const serialBtn = document.createElement('button');
                        serialBtn.type = 'button';
                        serialBtn.className = 'line-serial-btn';
                        serialBtn.title = 'Select Serial Numbers';
                        serialBtn.innerHTML = '<i class="bi bi-record-circle"></i>';
                        actionsWrap.append(serialBtn, removeBtn);
                    }
                }
                if (!row.querySelector('.line-selected-serials')) {
                    const serialInput = document.createElement('input');
                    serialInput.type = 'hidden';
                    serialInput.dataset.name = 'selected_serials';
                    serialInput.className = 'line-selected-serials';
                    serialInput.value = '';
                    row.appendChild(serialInput);
                }

                // Wrap Details Cell elements
                let detailsCell = row.querySelector('.line-details-cell');
                const prodSelect = row.querySelector('.line-product');
                if (!detailsCell && prodSelect) {
                    detailsCell = document.createElement('div');
                    detailsCell.className = 'line-details-cell';
                    prodSelect.before(detailsCell);
                    detailsCell.appendChild(prodSelect);
                }

                if (detailsCell) {
                    let lotPickerWrap = detailsCell.querySelector('.line-lot-picker-wrap');
                    if (!lotPickerWrap) {
                        lotPickerWrap = document.createElement('div');
                        lotPickerWrap.className = 'line-lot-picker-wrap hidden';
                        const lotBadge = document.createElement('span');
                        lotBadge.className = 'line-lot-badge';
                        lotBadge.innerHTML = '<i class="bi bi-box-seam"></i> Lot';
                        const lotPicker = document.createElement('select');
                        lotPicker.className = 'line-lot-picker';
                        lotPicker.setAttribute('aria-label', 'Select Lot');
                        lotPickerWrap.append(lotBadge, lotPicker);
                        detailsCell.appendChild(lotPickerWrap);
                    }

                    let confirmedEl = detailsCell.querySelector('.line-details-confirmed');
                    if (!confirmedEl) {
                        confirmedEl = document.createElement('div');
                        confirmedEl.className = 'line-details-confirmed hidden';
                        const titleEl = document.createElement('span');
                        titleEl.className = 'line-details-title';
                        const infoBtn = document.createElement('button');
                        infoBtn.type = 'button';
                        infoBtn.className = 'btn-item-info';
                        infoBtn.title = 'Item info';
                        infoBtn.innerHTML = '<i class="bi bi-info-circle-fill"></i>';
                        confirmedEl.append(titleEl, infoBtn);
                        detailsCell.appendChild(confirmedEl);
                    }

                    const lotPicker = detailsCell.querySelector('.line-lot-picker');
                    const confirmed = detailsCell.querySelector('.line-details-confirmed');
                    const titleEl = confirmed.querySelector('.line-details-title');
                    const infoBtn = confirmed.querySelector('.btn-item-info');

                    const productId = prodSelect?.value;
                    const product = products[productId];
                    const isService = row.dataset.targetKind === 'service' || row.dataset.kind === 'service' || row.querySelector('[data-name="line_type"]')?.value === 'service' || (product && !product.stock);
                    const locationId = locationSelect ? locationSelect.value : '';
                    const lotSelect = row.querySelector('.line-lot');
                    let currentLotId = lotSelect?.value;

                    const pLots = (lots[productId] || []).filter(stock => (!locationId || String(stock.location_id) === String(locationId)) && stock.available > 0);

                    // Re-wire click events if not already bound
                    if (!detailsCell.dataset.eventsBound) {
                        detailsCell.dataset.eventsBound = '1';

                        prodSelect?.addEventListener('change', () => {
                            const val = prodSelect.value;
                            if (!val) {
                                row.classList.add('row-pending');
                                lotPickerWrap.classList.add('hidden');
                                confirmed.classList.add('hidden');
                                return;
                            }
                            const curProduct = products[val];
                            const availableLotsForProd = (lots[val] || []).filter(stock => (!locationId || String(stock.location_id) === String(locationId)) && stock.available > 0);

                            if (availableLotsForProd.length > 0) {
                                // Needs lot selection: keep product select visible, show lot picker underneath
                                row.classList.add('row-pending');
                                prodSelect.classList.remove('hidden');
                                confirmed.classList.add('hidden');
                                lotPickerWrap.classList.remove('hidden');

                                lotPicker.replaceChildren();
                                const placeholder = document.createElement('option');
                                placeholder.value = '';
                                placeholder.textContent = 'Select Lot / Batch...';
                                lotPicker.appendChild(placeholder);
                                availableLotsForProd.forEach(lot => {
                                    const opt = document.createElement('option');
                                    opt.value = lot.id;
                                    const price = lot.price > 0 ? lot.price : (curProduct?.selling_price || 0);
                                    const expText = lot.expires_at ? ` | Exp: ${String(lot.expires_at).substring(0, 10)}` : '';
                                    opt.textContent = `Lot: ${lot.number} (Qty: ${lot.available}) | Price: ${Number(price).toFixed(2)}${expText}`;
                                    lotPicker.appendChild(opt);
                                });
                                const changeOpt = document.createElement('option');
                                changeOpt.value = '__change_item__';
                                changeOpt.textContent = '← Change Product';
                                lotPicker.appendChild(changeOpt);
                                lotPicker.dataset.populatedFor = `${val}-${locationId}`;

                                setTimeout(() => lotPicker.focus(), 50);
                            } else {
                                // Check duplicate product without lots
                                const existingPaid = findExistingRow(val, null, 'paid', row);
                                const existingFree = findExistingRow(val, null, 'free', row);
                                if (existingPaid || existingFree) {
                                    showDuplicateModal({
                                        productId: val,
                                        productName: curProduct?.name || '',
                                        lotNumber: curProduct?.code || '',
                                        lotId: null,
                                        lotPrice: curProduct?.selling_price || 0,
                                        qty: parseFloat(row.querySelector('.line-qty')?.value || 1) || 1,
                                        sourceRow: row,
                                        isCatalog: false
                                    });
                                    return;
                                }

                                // No lots -> confirm immediately
                                row.classList.remove('row-pending');
                                row.dataset.rowStatus = 'confirmed';
                                prodSelect.classList.add('hidden');
                                lotPickerWrap.classList.add('hidden');
                                confirmed.classList.remove('hidden');
                                titleEl.textContent = `${val} | ${curProduct?.name || ''} | ${curProduct?.code || ''}`;
                                const priceInp = row.querySelector('.line-price');
                                if (priceInp) {
                                    priceInp.value = Number(curProduct?.selling_price || 0).toFixed(2);
                                    priceInp.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                                decorateRows();
                                if (typeof window.invoiceCalc === 'function') window.invoiceCalc();
                                if (typeof updateSummaryTotals === 'function') updateSummaryTotals();
                            }
                        });

                        lotPicker?.addEventListener('change', () => {
                            const val = lotPicker.value;
                            if (!val) return;
                            if (val === '__change_item__') {
                                prodSelect.value = '';
                                if (lotSelect) lotSelect.value = '';
                                row.classList.add('row-pending');
                                lotPickerWrap.classList.add('hidden');
                                prodSelect.classList.remove('hidden');
                                prodSelect.focus();
                                return;
                            }
                            const curProdId = prodSelect?.value;
                            const curProduct = products[curProdId];
                            const curLocationId = locationSelect ? locationSelect.value : '';
                            const curLots = (lots[curProdId] || []).filter(stock => (!curLocationId || String(stock.location_id) === String(curLocationId)) && stock.available > 0);
                            const chosen = curLots.find(l => String(l.id) === String(val));
                            const lotNum = chosen ? chosen.number : (curProduct?.code || '');

                            // DUPLICATE CHECK: same product AND same lot!
                            const existingPaid = findExistingRow(curProdId, val, 'paid', row);
                            const existingFree = findExistingRow(curProdId, val, 'free', row);
                            if (existingPaid || existingFree) {
                                showDuplicateModal({
                                    productId: curProdId,
                                    productName: curProduct?.name || '',
                                    lotNumber: lotNum,
                                    lotId: val,
                                    lotPrice: (chosen?.price > 0 ? chosen.price : (curProduct?.selling_price || 0)),
                                    qty: parseFloat(row.querySelector('.line-qty')?.value || 1) || 1,
                                    sourceRow: row,
                                    isCatalog: false
                                });
                                return;
                            }

                            if (lotSelect) {
                                if (![...lotSelect.options].some(o => o.value === String(val))) {
                                    const opt = document.createElement('option');
                                    opt.value = val;
                                    opt.textContent = chosen ? `Lot: ${chosen.number}` : String(val);
                                    lotSelect.appendChild(opt);
                                }
                                lotSelect.value = val;
                                lotSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            }

                            if (chosen) {
                                const priceInp = row.querySelector('.line-price');
                                if (priceInp) {
                                    priceInp.value = (chosen.price > 0 ? chosen.price : (curProduct?.selling_price || 0)).toFixed(2);
                                    priceInp.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }
                            titleEl.textContent = `${curProdId} | ${curProduct?.name || ''} | ${lotNum}`;
                            const codeEl = row.querySelector('.reference-line-code');
                            if (codeEl) codeEl.textContent = lotNum;

                            row.classList.remove('row-pending');
                            row.dataset.rowStatus = 'confirmed';

                            // Hide both product select and lot picker, reveal confirmed view
                            prodSelect.classList.add('hidden');
                            lotPickerWrap.classList.add('hidden');
                            confirmed.classList.remove('hidden');

                            decorateRows();
                            if (typeof window.invoiceCalc === 'function') window.invoiceCalc();
                            if (typeof updateSummaryTotals === 'function') updateSummaryTotals();
                        });

                        infoBtn?.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            const curProdId = row.querySelector('.line-product')?.value;
                            if (curProdId) openItemInfoModal(curProdId);
                        });
                    }

                    if (isService) {
                        if (prodSelect) {
                            if (servicesMetadata && servicesMetadata.length > 0) {
                                prodSelect.classList.add('hidden');
                            } else {
                                prodSelect.classList.remove('hidden');
                            }
                        }
                        if (lotPickerWrap) lotPickerWrap.classList.add('hidden');
                        const serviceBox = detailsCell.querySelector('.service-autocomplete-box');
                        const srvInput = row.querySelector('[data-name="service_id"]');
                        const descVal = row.querySelector('.line-description')?.value;
                        const priceVal = parseFloat(row.querySelector('.line-price')?.value || 0) || 0;
                        const isConfirmed = row.dataset.rowStatus === 'confirmed' || (srvInput?.value || (descVal && descVal.trim()) || priceVal > 0);
                        if (isConfirmed && row.dataset.rowStatus !== 'pending') {
                            row.classList.remove('row-pending');
                            row.dataset.rowStatus = 'confirmed';
                            if (serviceBox) serviceBox.classList.add('hidden');
                            if (confirmed) {
                                confirmed.classList.remove('hidden');
                                const curSrvId = srvInput?.value;
                                const curSrv = curSrvId ? (servicesMetadata || []).find(s => String(s.id) === String(curSrvId)) : null;
                                if (curSrv && titleEl) titleEl.textContent = `${curSrv.code} | ${curSrv.name}`;
                                else if (descVal && titleEl) titleEl.textContent = descVal;
                            }
                        } else {
                            row.classList.add('row-pending');
                            row.dataset.rowStatus = 'pending';
                            if (serviceBox) {
                                serviceBox.classList.remove('hidden');
                                const pBtn = serviceBox.querySelector('.service-placeholder-btn');
                                const inWrap = serviceBox.querySelector('.service-input-wrap');
                                const sList = serviceBox.querySelector('.service-dropdown-list');
                                if (inWrap && !inWrap.classList.contains('active-editing')) {
                                    if (pBtn) pBtn.classList.remove('hidden');
                                    inWrap.classList.add('hidden');
                                    if (sList) sList.classList.add('hidden');
                                }
                            }
                            if (confirmed) confirmed.classList.add('hidden');
                        }
                    } else if (!productId) {
                        row.classList.add('row-pending');
                        prodSelect.classList.remove('hidden');
                        lotPickerWrap.classList.add('hidden');
                        confirmed.classList.add('hidden');
                    } else if (pLots.length > 0) {
                        if (!currentLotId && !row.classList.contains('row-pending')) {
                            currentLotId = String(pLots[0].id);
                            if (lotSelect) {
                                if (![...lotSelect.options].some(o => o.value === currentLotId)) {
                                    const opt = document.createElement('option');
                                    opt.value = currentLotId;
                                    opt.textContent = `Lot: ${pLots[0].number}`;
                                    lotSelect.appendChild(opt);
                                }
                                lotSelect.value = currentLotId;
                            }
                        }
                        if (currentLotId && pLots.some(l => String(l.id) === String(currentLotId))) {
                            // Confirmed with lot (Photo 1 & Photo 3)
                            row.classList.remove('row-pending');
                            row.dataset.rowStatus = 'confirmed';
                            prodSelect.classList.add('hidden');
                            lotPickerWrap.classList.add('hidden');
                            confirmed.classList.remove('hidden');
                            const chosenLot = pLots.find(l => String(l.id) === String(currentLotId));
                            const lotNumber = chosenLot ? chosenLot.number : (product?.code || '');
                            if (!row.dataset.isFreeIssue) {
                                titleEl.textContent = `${productId} | ${product?.name || ''} | ${lotNumber}`;
                            }
                            const codeEl = row.querySelector('.reference-line-code');
                            if (codeEl) codeEl.textContent = lotNumber;
                        } else {
                            // Needs lot selection: product select is visible, lot picker is visible underneath
                            row.classList.add('row-pending');
                            prodSelect.classList.remove('hidden');
                            confirmed.classList.add('hidden');
                            lotPickerWrap.classList.remove('hidden');

                            if (lotPicker.dataset.populatedFor !== `${productId}-${locationId}`) {
                                lotPicker.replaceChildren();
                                const placeholder = document.createElement('option');
                                placeholder.value = '';
                                placeholder.textContent = 'Select Lot / Batch...';
                                lotPicker.appendChild(placeholder);
                                pLots.forEach(lot => {
                                    const opt = document.createElement('option');
                                    opt.value = lot.id;
                                    const price = lot.price > 0 ? lot.price : (product?.selling_price || 0);
                                    const expText = lot.expires_at ? ` | Exp: ${String(lot.expires_at).substring(0, 10)}` : '';
                                    opt.textContent = `Lot: ${lot.number} (Qty: ${lot.available}) | Price: ${Number(price).toFixed(2)}${expText}`;
                                    lotPicker.appendChild(opt);
                                });
                                const changeOpt = document.createElement('option');
                                changeOpt.value = '__change_item__';
                                changeOpt.textContent = '← Change Product';
                                lotPicker.appendChild(changeOpt);
                                lotPicker.dataset.populatedFor = `${productId}-${locationId}`;
                            }
                        }
                    } else {
                        // Confirmed without lot (Photo 3)
                        row.classList.remove('row-pending');
                        row.dataset.rowStatus = 'confirmed';
                        prodSelect.classList.add('hidden');
                        lotPickerWrap.classList.add('hidden');
                        confirmed.classList.remove('hidden');
                        titleEl.textContent = `${productId} | ${product?.name || ''} | ${product?.code || ''}`;
                    }
                }

                // Metadata tags & available quantities
                const product = products[row.querySelector('.line-product')?.value];
                const isService = row.dataset.targetKind === 'service' || row.dataset.kind === 'service' || row.querySelector('[data-name="line_type"]')?.value === 'service' || (product && !product.stock);
                const isNote = row.dataset.kind === 'note' || row.classList.contains('odoo-text-line');
                const badge = row.querySelector('.reference-line-type');
                if (badge) {
                    badge.textContent = isService ? 'Service' : (isNote ? 'Text' : 'Stock');
                }
                const codeEl = row.querySelector('.reference-line-code');
                if (codeEl) {
                    if (isService) {
                        const srvInput = row.querySelector('[data-name="service_id"]');
                        const curSrv = srvInput?.value ? (servicesMetadata || []).find(s => String(s.id) === String(srvInput.value)) : null;
                        codeEl.textContent = curSrv?.code || (product?.code || 'SRV');
                    } else if (product) {
                        codeEl.textContent = product.code || '—';
                    } else if (isNote) {
                        codeEl.textContent = '—';
                    } else {
                        codeEl.textContent = '—';
                    }
                }
                const productId = row.querySelector('.line-product')?.value;
                const locationId = locationSelect ? locationSelect.value : '';
                const lotId = row.querySelector('.line-lot')?.value;
                const quantities = product?.lots || lotId ? (lots[productId] || []) : (product?.quantities || []);
                const available = quantities.filter(stock => (!locationId || String(stock.location_id) === locationId) && (!lotId || String(stock.id) === lotId))
                    .reduce((sum, stock) => sum + Number(stock.available), 0);
                const availEl = row.querySelector('.reference-line-available');
                if (availEl) {
                    availEl.textContent = isService ? '—' : (product?.stock ? available.toLocaleString(undefined, {maximumFractionDigits:4}) : '0');
                }
                if (isService) {
                    const lotSelect = row.querySelector('.line-lot');
                    if (lotSelect && !lotSelect.disabled) {
                        lotSelect.disabled = true;
                        lotSelect.replaceChildren();
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = 'No available lots';
                        lotSelect.appendChild(opt);
                    }
                }
            });
        }
        new MutationObserver(decorateRows).observe(lines, {childList:true});
        lines.addEventListener('change', decorateRows);
        locationSelect?.addEventListener('change', decorateRows);
        decorateRows();

        function createItemOrServiceRow(type) {
            let newRow = null;
            if (typeof window.invoiceAddRow === 'function') {
                newRow = window.invoiceAddRow(type === 'service' ? 'service' : 'product');
            } else if (add && typeof add.onclick === 'function') {
                const countBefore = lines.children.length;
                add.onclick();
                if (lines.children.length > countBefore) {
                    newRow = lines.children[lines.children.length - 1];
                }
            }
            if (!newRow) {
                const tpl = document.getElementById('invoice-line-template');
                if (tpl) {
                    newRow = tpl.content.firstElementChild.cloneNode(true);
                    lines.appendChild(newRow);
                }
            }
            if (newRow) {
                configureRow(newRow, type);
            }
            return newRow;
        }

        function createTextRow() {
            let newRow = null;
            if (typeof window.invoiceAddRow === 'function') {
                newRow = window.invoiceAddRow('note');
            } else if (note && typeof note.onclick === 'function') {
                const countBefore = lines.children.length;
                note.onclick();
                if (lines.children.length > countBefore) {
                    newRow = lines.children[lines.children.length - 1];
                }
            }
            if (!newRow) {
                const tpl = document.getElementById('text-line-template');
                if (tpl) {
                    newRow = tpl.content.firstElementChild.cloneNode(true);
                    lines.appendChild(newRow);
                }
            }
            return newRow;
        }

        function handleRowAdd(type, shouldFocus = true) {
            const rows = [...lines.children].filter(el => el.classList.contains('odoo-line') || el.classList.contains('odoo-text-line'));
            const lastRow = rows.length > 0 ? rows[rows.length - 1] : null;

            // If the last row is completely untouched/empty
            if (lastRow && isRowEmpty(lastRow)) {
                const currentType = getRowType(lastRow);

                // If it's already the same type, keep it and focus
                if (currentType === type) {
                    if (shouldFocus) focusRow(lastRow, type);
                    return lastRow;
                }

                // If switching between item and service (both are .odoo-line)
                if ((currentType === 'item' && type === 'service') || (currentType === 'service' && type === 'item')) {
                    configureRow(lastRow, type);
                    decorateRows();
                    if (shouldFocus) focusRow(lastRow, type);
                    if (typeof updateSummaryTotals === 'function') updateSummaryTotals();
                    return lastRow;
                }

                // If switching between odoo-line and odoo-text-line, replace it
                lastRow.remove();
                const newRow = type === 'text' ? createTextRow() : createItemOrServiceRow(type);
                decorateRows();
                if (shouldFocus) focusRow(newRow, type);
                if (typeof updateSummaryTotals === 'function') updateSummaryTotals();
                return newRow;
            }

            // Otherwise append a new row
            const newRow = type === 'text' ? createTextRow() : createItemOrServiceRow(type);
            decorateRows();
            if (shouldFocus) focusRow(newRow, type);
            if (typeof updateSummaryTotals === 'function') updateSummaryTotals();
            return newRow;
        }

        if (add) {
            add.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopImmediatePropagation();
                handleRowAdd('item', true);
            }, true);
        }

        if (note) {
            note.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopImmediatePropagation();
                handleRowAdd('text', true);
            }, true);
        }

        service.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            handleRowAdd('service', false);
        });

        // Add first item row by default if form is empty on create
        if (!shell.hasAttribute('data-invoice-locked') && lines.children.length === 0) {
            handleRowAdd('item', false);
        }
        shell.querySelector('.reference-order-summary')?.classList.toggle('hidden', !shell.querySelector('[name="sales_order_id"]'));
        // Interactive Payment Terms (Photos 2 & 3)
        const btnTogglePaymentTerms = document.getElementById('btn-toggle-payment-terms');
        const ptSentenceView = document.getElementById('pt-sentence-view');
        const ptSentenceEdit = document.getElementById('pt-sentence-edit');
        const ptDaysDisplay = document.getElementById('pt-days-display');
        const ptDaysInput = document.getElementById('pt-days-input');
        const ptActionIcon = document.getElementById('pt-action-icon');
        const ptToast = document.getElementById('payment-terms-toast');
        const invDateInput = document.getElementById('invoice-date-input') || shell.querySelector('[name="invoice_date"]');
        const dueDateInput = document.getElementById('invoice-due-date');
        const paymentTermHidden = document.getElementById('invoice-payment-term');

        function updateDueDateFromDays(days) {
            const baseDate = invDateInput?.value ? new Date(invDateInput.value + 'T00:00:00') : new Date();
            baseDate.setDate(baseDate.getDate() + parseInt(days || 30, 10));
            const yyyy = baseDate.getFullYear();
            const mm = String(baseDate.getMonth() + 1).padStart(2, '0');
            const dd = String(baseDate.getDate()).padStart(2, '0');
            if (dueDateInput) dueDateInput.value = `${yyyy}-${mm}-${dd}`;
        }

        // Restore saved default payment terms
        let savedDefaultDays = null;
        try {
            savedDefaultDays = window.localStorage ? window.localStorage.getItem('erp_default_payment_term_days') : null;
        } catch (e) {}

        if (savedDefaultDays && !shell.hasAttribute('data-invoice-locked')) {
            const daysNum = parseInt(savedDefaultDays, 10);
            if (ptDaysDisplay) ptDaysDisplay.textContent = daysNum;
            if (ptDaysInput) ptDaysInput.value = daysNum;
            if (paymentTermHidden) paymentTermHidden.value = `${daysNum} Days`;
            updateDueDateFromDays(daysNum);
        }

        if (btnTogglePaymentTerms) {
            btnTogglePaymentTerms.addEventListener('click', (e) => {
                e.preventDefault();
                const isEditing = !ptSentenceEdit.classList.contains('hidden');
                if (!isEditing) {
                    // Switch to edit mode (Photo 3)
                    ptSentenceView.classList.add('hidden');
                    ptSentenceEdit.classList.remove('hidden');
                    ptDaysInput.value = ptDaysDisplay.textContent.trim() || '30';
                    ptDaysInput.focus();
                    ptActionIcon.className = 'bi bi-floppy-fill';
                    btnTogglePaymentTerms.title = 'Save default payment terms';
                } else {
                    // Switch to view mode and save (Photo 2)
                    const newDays = parseInt(ptDaysInput.value || 30, 10);
                    ptDaysDisplay.textContent = newDays;
                    if (paymentTermHidden) paymentTermHidden.value = `${newDays} Days`;
                    updateDueDateFromDays(newDays);

                    try {
                        if (window.localStorage) window.localStorage.setItem('erp_default_payment_term_days', newDays);
                    } catch (e) {}

                    ptSentenceEdit.classList.add('hidden');
                    ptSentenceView.classList.remove('hidden');
                    ptActionIcon.className = 'bi bi-pencil-fill';
                    btnTogglePaymentTerms.title = 'Edit default payment terms';

                    // Show success toast (Photo 2)
                    if (ptToast) {
                        ptToast.classList.remove('hidden');
                        clearTimeout(window.__ptToastTimeout);
                        window.__ptToastTimeout = setTimeout(() => {
                            ptToast.classList.add('hidden');
                        }, 3500);
                    }
                }
            });
        }

        invDateInput?.addEventListener('change', () => {
            const days = parseInt(ptDaysDisplay?.textContent.trim() || 30, 10);
            updateDueDateFromDays(days);
        });

        // Real-time calculation for Summary Totals & Discounts (Photo 4)
        function updateSummaryTotals() {
            let untaxedTotal = 0;
            let taxTotal = 0;

            lines.querySelectorAll('.odoo-line').forEach(row => {
                if (row.classList.contains('row-pending')) return;
                const qty = parseFloat(row.querySelector('.line-qty')?.value || 0) || 0;
                const price = parseFloat(row.querySelector('.line-price')?.value || 0) || 0;
                const lineDisc = parseFloat(row.querySelector('.line-discount')?.value || 0) || 0;
                const gross = qty * price;
                const discAmt = gross * (lineDisc / 100);
                const raw = gross - discAmt;
                const taxSelect = row.querySelector('.line-tax');
                const rate = parseFloat(taxSelect?.options[taxSelect.selectedIndex]?.dataset.rate || 0) || 0;
                const inclusive = document.getElementById('tax-display')?.value === 'inclusive';
                const base = (inclusive && rate > 0) ? (raw / (1 + rate / 100)) : raw;
                const tax = inclusive ? (raw - base) : (base * rate / 100);

                const discSpan = row.querySelector('.line-discount-amount');
                if (discSpan) discSpan.textContent = `LKR ${discAmt.toFixed(2)}`;

                untaxedTotal += base;
                taxTotal += tax;
            });

            untaxedTotal = Math.round(untaxedTotal * 100) / 100;
            taxTotal = Math.round(taxTotal * 100) / 100;
            const grossSum = untaxedTotal + taxTotal;

            const pctInput = document.getElementById('summary-discount-percent');
            const amtInput = document.getElementById('summary-discount-amount');
            let pct = parseFloat(pctInput?.value || 0) || 0;
            let amt = parseFloat(amtInput?.value || 0) || 0;

            if (document.activeElement === pctInput) {
                amt = Math.round(untaxedTotal * pct / 100 * 100) / 100;
                if (amtInput) amtInput.value = amt.toFixed(2);
            } else if (document.activeElement === amtInput) {
                pct = untaxedTotal > 0 ? Math.round(amt / untaxedTotal * 100 * 100) / 100 : 0;
                if (pctInput) pctInput.value = pct.toFixed(2);
            } else {
                if (pct > 0 && amt === 0) {
                    amt = Math.round(untaxedTotal * pct / 100 * 100) / 100;
                    if (amtInput) amtInput.value = amt.toFixed(2);
                } else if (amt > 0 && pct === 0) {
                    pct = untaxedTotal > 0 ? Math.round(amt / untaxedTotal * 100 * 100) / 100 : 0;
                    if (pctInput) pctInput.value = pct.toFixed(2);
                }
            }

            const netTotal = Math.max(0, Math.round((grossSum - amt) * 100) / 100);

            const summaryTotal = document.getElementById('summary-total-display');
            if (summaryTotal) summaryTotal.value = untaxedTotal.toFixed(2);

            const summaryNet = document.getElementById('summary-net-total-display');
            if (summaryNet) summaryNet.value = netTotal.toFixed(2);

            const summaryTax = document.getElementById('summary-tax-display');
            if (summaryTax) summaryTax.value = taxTotal.toFixed(2);

            // Sync hidden elements for legacy hooks
            const uT = document.getElementById('untaxed-total');
            if (uT) uT.textContent = untaxedTotal.toFixed(2) + ' Rs';
            const gT = document.getElementById('grand-total');
            if (gT) gT.textContent = netTotal.toFixed(2) + ' Rs';
            const tT = document.getElementById('tax-total');
            if (tT) tT.textContent = taxTotal.toFixed(2) + ' Rs';
            const aD = document.getElementById('amount-due');
            if (aD) aD.textContent = netTotal.toFixed(2) + ' Rs';
        }

        // Attach listeners to lines & discount fields
        lines.addEventListener('input', updateSummaryTotals);
        lines.addEventListener('change', updateSummaryTotals);
        document.getElementById('summary-discount-percent')?.addEventListener('input', updateSummaryTotals);
        document.getElementById('summary-discount-amount')?.addEventListener('input', updateSummaryTotals);
        // Auto-clear typing fields on mouse click (Summary Discount %, Discount Amount, Line Price, Qty, Line Discount)
        function isTypingField(target) {
            if (!target || target.readOnly || target.disabled) return false;
            if (target.id === 'summary-discount-percent' || target.id === 'summary-discount-amount') return true;
            if (target.classList.contains('line-qty') || target.classList.contains('line-price') || target.classList.contains('line-discount')) return true;
            if (target.classList.contains('ref-summary-box') && target.classList.contains('editable')) return true;
            return false;
        }

        shell.addEventListener('click', (e) => {
            const target = e.target;
            if (isTypingField(target)) {
                if (!target.dataset.hasClearedOnEntry) {
                    target.dataset.prevVal = target.value;
                    target.value = '';
                    target.dataset.hasClearedOnEntry = '1';
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        }, true);

        shell.addEventListener('input', (e) => {
            const target = e.target;
            if (isTypingField(target)) {
                target.dataset.hasClearedOnEntry = '1';
            }
        }, true);

        shell.addEventListener('focus', (e) => {
            const target = e.target;
            if (isTypingField(target)) {
                target.dataset.prevVal = target.value;
                if (target.value === '0' || target.value === '0.00' || parseFloat(target.value) === 0) {
                    target.value = '';
                    target.dataset.hasClearedOnEntry = '1';
                } else {
                    target.select();
                }
            }
        }, true);

        shell.addEventListener('blur', (e) => {
            const target = e.target;
            if (isTypingField(target)) {
                delete target.dataset.hasClearedOnEntry;
                if (target.value.trim() === '') {
                    let fallback = '0';
                    if (target.classList.contains('line-qty')) fallback = '1';
                    else if (target.classList.contains('line-price')) fallback = '0.00';
                    target.value = fallback;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }, true);

        const termsEl = document.getElementById('invoice-terms');
        if (termsEl) {
            termsEl.addEventListener('focus', function() {
                if (this.value.trim() === '1') this.value = '';
            });
            termsEl.addEventListener('click', function() {
                if (this.value.trim() === '1') this.value = '';
            });
        }

        const invoiceForm = document.getElementById('invoice-form');
        invoiceForm?.addEventListener('submit', () => {
            const rows = [...lines.children].filter(el => el.classList.contains('odoo-line') || el.classList.contains('odoo-text-line'));
            if (rows.length > 1) {
                rows.forEach(row => {
                    if (isRowEmpty(row)) {
                        row.remove();
                    }
                });
            }
        });

        updateSummaryTotals();

        // Also hook into window.invoiceCalc
        const origCalc = window.invoiceCalc;
        window.invoiceCalc = function() {
            if (origCalc) origCalc();
            updateSummaryTotals();
        };

        shell.querySelector('.reference-order-summary')?.classList.toggle('hidden', !shell.querySelector('[name="sales_order_id"]'));
        // A persistent footer leaves the primary actions visible as the form scrolls.
        const sheet = shell.querySelector('.odoo-sheet');
        const footer = sheet.querySelector('.reference-invoice-footer');
        const scroll = document.createElement('div');
        scroll.className = 'reference-form-scroll';
        [...sheet.children].filter(child => child !== footer).forEach(child => scroll.append(child));
        sheet.prepend(scroll);
        shell.querySelector('.odoo-tabs').classList.add('reference-secondary-tabs');
        const advanced = document.createElement('details');
        advanced.className = 'reference-advanced';
        const summary = document.createElement('summary');
        summary.textContent = 'Invoice options';
        advanced.append(summary, shell.querySelector('.odoo-tabs'));
        scroll.append(advanced);
        document.getElementById('reference-orders')?.addEventListener('click', () => document.getElementById('orders-dialog')?.showModal());

        const ordersDialog = document.getElementById('orders-dialog');
        const conflictDialog = document.getElementById('order-conflict-dialog');
        const conflictOrderNumber = document.getElementById('conflict-order-number');
        const btnConflictClearAdd = document.getElementById('btn-conflict-clear-add');
        const btnConflictCancel = document.getElementById('btn-conflict-cancel');
        let pendingOrderUrl = '';

        ordersDialog?.addEventListener('click', (e) => {
            const selectBtn = e.target.closest('a[href*="order="]');
            if (!selectBtn) return;

            const clickedOrderId = selectBtn.dataset.orderId || (selectBtn.href.match(/order=(\d+)/)?.[1]);
            const clickedOrderNumber = selectBtn.dataset.orderNumber || selectBtn.closest('tr')?.querySelector('td')?.textContent?.trim() || '';
            const targetUrl = selectBtn.href;

            const currentOrderId = document.querySelector('[name="sales_order_id"]')?.value;
            const currentOrderBadge = document.getElementById('ref-order-selected-badge');
            const customerId = document.querySelector('[name="customer_id"]')?.value;

            let hasNonEmptyLines = false;
            lines?.querySelectorAll('.odoo-line, .odoo-text-line').forEach(row => {
                const prod = row.querySelector('.line-product')?.value;
                const srv = row.querySelector('[data-name="service_id"]')?.value;
                const desc = row.querySelector('.line-description')?.value;
                const price = parseFloat(row.querySelector('.line-price')?.value || 0) || 0;
                if (prod || srv || (desc && desc.trim()) || price > 0) {
                    hasNonEmptyLines = true;
                }
            });

            const hasExistingData = !!(currentOrderId || currentOrderBadge || (customerId && hasNonEmptyLines));

            if (hasExistingData && String(currentOrderId) !== String(clickedOrderId)) {
                e.preventDefault();
                e.stopPropagation();

                pendingOrderUrl = targetUrl;
                if (conflictOrderNumber) conflictOrderNumber.textContent = clickedOrderNumber;

                if (typeof ordersDialog.close === 'function') ordersDialog.close();
                if (conflictDialog) {
                    if (typeof conflictDialog.showModal === 'function') conflictDialog.showModal();
                    else conflictDialog.setAttribute('open', '');
                }
            }
        });

        btnConflictClearAdd?.addEventListener('click', () => {
            if (pendingOrderUrl) {
                window.location.href = pendingOrderUrl;
            }
        });

        btnConflictCancel?.addEventListener('click', () => {
            if (conflictDialog) {
                if (typeof conflictDialog.close === 'function') conflictDialog.close();
                else conflictDialog.removeAttribute('open');
            }
        });

        conflictDialog?.addEventListener('click', (e) => {
            if (e.target === conflictDialog) {
                if (typeof conflictDialog.close === 'function') conflictDialog.close();
                else conflictDialog.removeAttribute('open');
            }
        });

        const customerSelect = shell.querySelector('[name="customer_id"]');
        const customerSearchInput = document.getElementById('reference-customer-search');
        const ledgerPanel = document.getElementById('tab-journal');
        let ledgerDialog = document.getElementById('invoice-ledger-dialog');

        if (!ledgerDialog) {
            // Fallback for tests/environments where blade template dialogs aren't mounted
            ledgerDialog = document.createElement('dialog');
            ledgerDialog.id = 'invoice-ledger-dialog';
            ledgerDialog.className = 'invoice-flow-dialog reference-ledger-dialog';
            const closeLedger = document.createElement('button');
            closeLedger.type = 'button';
            closeLedger.textContent = 'Close Ledger';
            closeLedger.addEventListener('click', () => {
                ledgerDialog.close();
                shell.querySelector('[data-tab="lines"]')?.click();
            });
            ledgerDialog.append(closeLedger);
            document.getElementById('tab-lines')?.after(ledgerDialog);
        }

        const closeLedgerModal = () => {
            if (typeof ledgerDialog.close === 'function') ledgerDialog.close();
            else ledgerDialog.removeAttribute('open');
            shell.querySelector('[data-tab="lines"]')?.click();
        };

        document.getElementById('btn-close-ledger-top')?.addEventListener('click', closeLedgerModal);
        document.getElementById('btn-close-ledger-bottom')?.addEventListener('click', closeLedgerModal);
        ledgerDialog.addEventListener('click', (e) => {
            if (e.target === ledgerDialog) closeLedgerModal();
        });
        ledgerDialog.addEventListener('close', () => {
            shell.querySelector('[data-tab="lines"]')?.click();
        });

        shell.querySelector('[data-tab="journal"]')?.addEventListener('click', () => {
            document.getElementById('tab-lines')?.classList.remove('hidden');
            if (ledgerPanel && !ledgerDialog.contains(ledgerPanel) && !document.getElementById('reference-ledger-rows')) {
                ledgerDialog.append(ledgerPanel);
            }
            if (!ledgerDialog.open && typeof ledgerDialog.showModal === 'function') ledgerDialog.showModal();
        });

        const ledgerBtn = document.getElementById('reference-ledger');
        if (ledgerBtn) {
            ledgerBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const custId = customerSelect?.value;
                const errorDialog = document.getElementById('invoice-error-dialog');

                if (errorDialog) {
                    if (!custId) {
                        if (window.showInvoiceError) {
                            window.showInvoiceError('Please select a customer.');
                        } else {
                            alert('Please select a customer.');
                        }
                        return;
                    }

                    const validItemRows = [...lines.querySelectorAll('.odoo-line')].filter(row => {
                        const prod = row.querySelector('.line-product');
                        const qty = parseFloat(row.querySelector('.line-qty')?.value || 0);
                        return prod && prod.value && qty > 0;
                    });

                    if (validItemRows.length === 0) {
                        if (window.showInvoiceError) {
                            window.showInvoiceError('Please select at least one item.');
                        } else {
                            alert('Please select at least one item.');
                        }
                        return;
                    }
                }

                if (!document.getElementById('reference-ledger-rows')) {
                    shell.querySelector('[data-tab="journal"]')?.click();
                    return;
                }

                // Render Ledger Preview Modal (Photo 1 & Photo 2)
                const invDate = shell.querySelector('[name="invoice_date"]')?.value || new Date().toISOString().slice(0, 10);
                const invNumber = document.getElementById('invoice-number-display')?.textContent.trim() || shell.querySelector('.odoo-number')?.value.trim() || '0813';
                const custName = customerSearchInput?.value || 'Customer';

                const untaxedText = document.getElementById('untaxed-total')?.textContent.replace(/[^0-9.-]+/g, '') || '0';
                const taxText = document.getElementById('tax-total')?.textContent.replace(/[^0-9.-]+/g, '') || '0';
                const grandText = document.getElementById('grand-total')?.textContent.replace(/[^0-9.-]+/g, '') || '0';

                const untaxedTotal = parseFloat(untaxedText) || 0;
                const taxTotal = parseFloat(taxText) || 0;
                const grandTotal = parseFloat(grandText) || (untaxedTotal + taxTotal);

                // Cost of goods sold: calculate from products or 60%
                let costTotal = 0;
                const validRows = [...lines.querySelectorAll('.odoo-line')].filter(row => {
                    const prod = row.querySelector('.line-product');
                    const qty = parseFloat(row.querySelector('.line-qty')?.value || 0);
                    return prod && prod.value && qty > 0;
                });

                validRows.forEach(row => {
                    const pId = row.querySelector('.line-product')?.value;
                    const qty = parseFloat(row.querySelector('.line-qty')?.value || 1);
                    const pMeta = products[pId];
                    const unitPrice = parseFloat(row.querySelector('.line-price')?.value || 0);
                    const pCost = (pMeta && pMeta.purchase_price > 0) ? pMeta.purchase_price : (unitPrice * 0.6);
                    costTotal += (pCost * qty);
                });
                costTotal = Math.round(costTotal * 100) / 100;
                if (costTotal <= 0 && grandTotal > 0) {
                    costTotal = Math.round(grandTotal * 0.6 * 100) / 100;
                }

                const totalDebit = Math.round((grandTotal + costTotal) * 100) / 100;
                const totalCredit = Math.round((untaxedTotal + taxTotal + costTotal) * 100) / 100;
                const balance = Math.abs(Math.round((totalDebit - totalCredit) * 100) / 100);

                // Update Cards
                const totalDebitEl = document.getElementById('ref-ledger-total-debit');
                const totalCreditEl = document.getElementById('ref-ledger-total-credit');
                const balanceEl = document.getElementById('ref-ledger-balance');
                if (totalDebitEl) totalDebitEl.textContent = totalDebit.toFixed(2);
                if (totalCreditEl) totalCreditEl.textContent = totalCredit.toFixed(2);
                if (balanceEl) balanceEl.textContent = balance.toFixed(2);

                // Update Table Rows
                const tbody = document.getElementById('reference-ledger-rows');
                if (tbody) {
                    tbody.replaceChildren();
                    const addRow = (date, account, code, desc, debit, credit) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${date}</td>
                            <td><strong class="ref-acc-name">${account}</strong></td>
                            <td style="text-align: center;">${code}</td>
                            <td>${desc}</td>
                            <td style="text-align: right;">${debit ? `<span class="ref-pill-badge debit">${debit.toFixed(2)}</span>` : `<span class="ref-pill-dash">-</span>`}</td>
                            <td style="text-align: right;">${credit ? `<span class="ref-pill-badge credit">${credit.toFixed(2)}</span>` : `<span class="ref-pill-dash">-</span>`}</td>
                        `;
                        tbody.appendChild(tr);
                    };

                    // 1. Customer Debtor
                    addRow(invDate, `Customer Debtor (${custName})`, '8', `Sale (Inv/No: ${invNumber}) (Cus: ${custName})`, grandTotal, 0);

                    // 2. Sales
                    addRow(invDate, 'Sales', '3', `Sale (Inv/No: ${invNumber}) (Cus: ${custName})`, 0, untaxedTotal);

                    // 3. Tax Payable (if tax > 0)
                    if (taxTotal > 0) {
                        addRow(invDate, 'Tax Payable', '5', `Tax (Inv/No: ${invNumber}) (Cus: ${custName})`, 0, taxTotal);
                    }

                    // 4. Stock Account
                    addRow(invDate, 'Stock Account', '1', `Stock (Inv/No: ${invNumber}) (Cus: ${custName})`, 0, costTotal);

                    // 5. Cost of Sales
                    addRow(invDate, 'Cost of Sales', '2', `Cost of Sales (Inv/No: ${invNumber}) (Cus: ${custName})`, costTotal, 0);
                }

                // Update Footers
                const footDebit = document.getElementById('ref-ledger-foot-debit');
                const footCredit = document.getElementById('ref-ledger-foot-credit');
                if (footDebit) footDebit.textContent = totalDebit.toFixed(2);
                if (footCredit) footCredit.textContent = totalCredit.toFixed(2);

                if (ledgerDialog) {
                    if (typeof ledgerDialog.showModal === 'function') ledgerDialog.showModal();
                    else ledgerDialog.setAttribute('open', '');
                }
            });
        }
        const table = document.querySelector('#orders-dialog table');
        if (table && window.jQuery?.fn.DataTable) {
            jQuery(table).DataTable({pageLength:10, order:[[0,'desc']], columnDefs:[{targets:5,orderable:false}], language:{search:'Search:'}});
        }
        const dialog = document.getElementById('orders-dialog');
        dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });

        // ==========================================================================
        // Select Serial Numbers Dialog Handling (Photos 3 & 4)
        // ==========================================================================
        const serialsDialog = document.getElementById('select-serials-dialog');
        const btnCloseSerialsX = document.getElementById('btn-close-serials-dialog');
        const btnSerialsCancel = document.getElementById('btn-serials-cancel');
        const btnSerialsConfirm = document.getElementById('btn-serials-confirm');
        const btnSerialsFind = document.getElementById('btn-serials-find');
        const btnSerialsBrowse = document.getElementById('btn-serials-browse');
        const serialsSearchInput = document.getElementById('serials-search-input');
        const serialsTableBody = document.getElementById('serials-table-body');
        const serialsCheckAll = document.getElementById('serials-check-all');
        const serialsProductTitle = document.getElementById('serials-product-title');
        const serialsRequiredQty = document.getElementById('serials-required-qty');
        const serialsSelectedCount = document.getElementById('serials-selected-count');
        const serialsSelectedTotalLabel = document.getElementById('serials-selected-total-label');
        const serialsEmptyMessage = document.getElementById('serials-empty-message');
        const serialsExcelFile = document.getElementById('serials-excel-file');
        const serialsFileChosenText = document.getElementById('serials-file-chosen-text');
        const btnSerialsUpload = document.getElementById('btn-serials-upload');

        let activeSerialRow = null;
        let currentAvailableSerials = [];
        let selectedSerialSet = new Set();
        let targetRequiredQty = 1;

        function updateSerialSelectionCount() {
            if (serialsSelectedCount) serialsSelectedCount.textContent = selectedSerialSet.size;
            if (serialsCheckAll) {
                serialsCheckAll.checked = selectedSerialSet.size > 0 && selectedSerialSet.size === Math.min(targetRequiredQty, currentAvailableSerials.length);
            }
        }

        function renderSerialsTable(filterText = '') {
            if (!serialsTableBody) return;
            serialsTableBody.replaceChildren();

            const query = (filterText || '').trim().toLowerCase();
            const filtered = currentAvailableSerials.filter(item => {
                if (!query) return true;
                return item.serial_number.toLowerCase().includes(query) ||
                       (item.description || '').toLowerCase().includes(query);
            });

            if (filtered.length === 0) {
                if (serialsEmptyMessage) serialsEmptyMessage.classList.remove('hidden');
            } else {
                if (serialsEmptyMessage) serialsEmptyMessage.classList.add('hidden');
                filtered.forEach(item => {
                    const tr = document.createElement('tr');
                    const isChecked = selectedSerialSet.has(item.serial_number);
                    if (isChecked) tr.classList.add('is-selected');

                    const tdCheck = document.createElement('td');
                    tdCheck.style.textAlign = 'center';
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.className = 'serial-row-check';
                    cb.checked = isChecked;
                    tdCheck.appendChild(cb);

                    const tdSn = document.createElement('td');
                    tdSn.innerHTML = `<strong>${item.serial_number}</strong>`;

                    const tdDesc = document.createElement('td');
                    tdDesc.textContent = item.description || 'Standard Serialized Unit';

                    const tdPrice = document.createElement('td');
                    tdPrice.style.textAlign = 'right';
                    tdPrice.textContent = item.price ? Number(item.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00';

                    const tdStatus = document.createElement('td');
                    tdStatus.style.textAlign = 'center';
                    tdStatus.innerHTML = `<span class="badge-status-avail">Available</span>`;

                    tr.append(tdCheck, tdSn, tdDesc, tdPrice, tdStatus);

                    const toggleSelection = () => {
                        if (selectedSerialSet.has(item.serial_number)) {
                            selectedSerialSet.delete(item.serial_number);
                            cb.checked = false;
                            tr.classList.remove('is-selected');
                        } else {
                            if (selectedSerialSet.size >= targetRequiredQty) {
                                alert(`Required quantity is ${targetRequiredQty}. You have already selected ${selectedSerialSet.size} serial number(s).`);
                                return;
                            }
                            selectedSerialSet.add(item.serial_number);
                            cb.checked = true;
                            tr.classList.add('is-selected');
                        }
                        updateSerialSelectionCount();
                    };

                    cb.addEventListener('change', (e) => {
                        e.stopPropagation();
                        if (cb.checked) {
                            if (selectedSerialSet.size >= targetRequiredQty) {
                                cb.checked = false;
                                alert(`Required quantity is ${targetRequiredQty}. You have already selected ${selectedSerialSet.size} serial number(s).`);
                                return;
                            }
                            selectedSerialSet.add(item.serial_number);
                            tr.classList.add('is-selected');
                        } else {
                            selectedSerialSet.delete(item.serial_number);
                            tr.classList.remove('is-selected');
                        }
                        updateSerialSelectionCount();
                    });

                    tr.addEventListener('click', (e) => {
                        if (e.target !== cb) {
                            toggleSelection();
                        }
                    });

                    serialsTableBody.appendChild(tr);
                });
            }
            updateSerialSelectionCount();
        }

        // Delegated click on line-serial-btn (Photo 3)
        lines?.addEventListener('click', (e) => {
            const serialBtn = e.target.closest('.line-serial-btn');
            if (!serialBtn) return;
            e.preventDefault();

            const row = serialBtn.closest('.odoo-line');
            if (!row) return;

            const productSelect = row.querySelector('.line-product');
            const productId = productSelect ? productSelect.value : '';
            if (!productId) {
                alert('Please select a product first before selecting serial numbers.');
                productSelect?.focus();
                return;
            }

            activeSerialRow = row;
            const pMeta = products[productId] || {};
            const pOption = productSelect.options[productSelect.selectedIndex];
            const pName = pMeta.name || pOption?.textContent?.replace(/\s*\[.*?\]$/, '') || 'Selected Product';
            const pCode = pMeta.code || '';
            const pPrice = parseFloat(row.querySelector('.line-price')?.value || pMeta.selling_price || 0);
            const qtyVal = parseFloat(row.querySelector('.line-qty')?.value || 1);
            targetRequiredQty = Math.max(1, Math.round(qtyVal));

            // Banner details matching Photo 4
            if (serialsProductTitle) {
                serialsProductTitle.textContent = `${productId} | ${pName} | ${pCode}`;
            }
            if (serialsRequiredQty) {
                serialsRequiredQty.textContent = targetRequiredQty;
            }
            if (serialsSelectedTotalLabel) {
                serialsSelectedTotalLabel.textContent = `/ ${targetRequiredQty}`;
            }

            // Load existing selection
            const hiddenSerials = row.querySelector('.line-selected-serials');
            const existing = (hiddenSerials?.value || '').split(',').map(s => s.trim()).filter(Boolean);
            selectedSerialSet = new Set(existing);

            // Fetch product's available serials from database metadata
            const dbSerials = (serialsMetadata[productId] || []).map(s => ({
                serial_number: s.serial_number,
                description: `${pName} - Lot #${s.product_lot_id || 'Main'}`,
                price: pPrice
            }));

            // Fallback / generate serials if database doesn't have enough preloaded
            if (dbSerials.length === 0) {
                const productLots = lots[productId] || [];
                if (productLots.length > 0) {
                    productLots.forEach((lot) => {
                        for (let n = 1; n <= Math.min(5, Math.ceil(lot.available)); n++) {
                            dbSerials.push({
                                serial_number: `${lot.number}-${String(n).padStart(3, '0')}`,
                                description: `${pName} · ${lot.location || 'Warehouse'}`,
                                price: pPrice
                            });
                        }
                    });
                } else {
                    const prefix = pCode ? pCode.replace(/[^a-zA-Z0-9-]/g, '') : 'SN';
                    for (let i = 1; i <= Math.max(10, targetRequiredQty + 5); i++) {
                        dbSerials.push({
                            serial_number: `${prefix}-2026-${String(i).padStart(4, '0')}`,
                            description: `${pName} Standard Unit`,
                            price: pPrice
                        });
                    }
                }
            }

            currentAvailableSerials = dbSerials;

            if (serialsSearchInput) serialsSearchInput.value = '';
            renderSerialsTable();

            if (serialsDialog) {
                if (typeof serialsDialog.showModal === 'function') serialsDialog.showModal();
                else serialsDialog.setAttribute('open', '');
            }
        });

        // Search and browse controls
        serialsSearchInput?.addEventListener('input', () => {
            renderSerialsTable(serialsSearchInput.value);
        });

        btnSerialsFind?.addEventListener('click', (e) => {
            e.preventDefault();
            renderSerialsTable(serialsSearchInput?.value || '');
        });

        btnSerialsBrowse?.addEventListener('click', (e) => {
            e.preventDefault();
            if (serialsSearchInput) serialsSearchInput.value = '';
            renderSerialsTable('');
        });

        serialsCheckAll?.addEventListener('change', () => {
            if (serialsCheckAll.checked) {
                selectedSerialSet.clear();
                for (let i = 0; i < Math.min(targetRequiredQty, currentAvailableSerials.length); i++) {
                    selectedSerialSet.add(currentAvailableSerials[i].serial_number);
                }
            } else {
                selectedSerialSet.clear();
            }
            renderSerialsTable(serialsSearchInput?.value || '');
        });

        // File upload chooser
        serialsExcelFile?.addEventListener('change', () => {
            if (serialsExcelFile.files && serialsExcelFile.files[0]) {
                serialsFileChosenText.textContent = serialsExcelFile.files[0].name;
            } else {
                serialsFileChosenText.textContent = 'No file chosen';
            }
        });

        btnSerialsUpload?.addEventListener('click', (e) => {
            e.preventDefault();
            if (!serialsExcelFile?.files?.length) {
                alert('Please choose an Excel or CSV file first.');
                return;
            }
            // Auto-select available serials up to required qty
            selectedSerialSet.clear();
            for (let i = 0; i < Math.min(targetRequiredQty, currentAvailableSerials.length); i++) {
                selectedSerialSet.add(currentAvailableSerials[i].serial_number);
            }
            renderSerialsTable(serialsSearchInput?.value || '');
            alert(`File parsed: Selected ${selectedSerialSet.size} serial number(s) from Excel.`);
        });

        // Confirm Selection button (Photo 4)
        btnSerialsConfirm?.addEventListener('click', (e) => {
            e.preventDefault();
            if (activeSerialRow) {
                let hiddenInput = activeSerialRow.querySelector('.line-selected-serials');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.className = 'line-selected-serials';
                    hiddenInput.dataset.name = 'selected_serials';
                    activeSerialRow.appendChild(hiddenInput);
                }
                const serialArray = Array.from(selectedSerialSet);
                hiddenInput.value = serialArray.join(',');

                const serialBtn = activeSerialRow.querySelector('.line-serial-btn');
                if (serialBtn) {
                    if (serialArray.length > 0) {
                        serialBtn.classList.add('has-serials');
                        serialBtn.title = `Selected Serials (${serialArray.length}): ${serialArray.join(', ')}`;
                    } else {
                        serialBtn.classList.remove('has-serials');
                        serialBtn.title = 'Select Serial Numbers';
                    }
                }
            }
            if (serialsDialog) {
                if (typeof serialsDialog.close === 'function') serialsDialog.close();
                else serialsDialog.removeAttribute('open');
            }
        });

        // Close handlers
        const closeSerialsModal = (e) => {
            e?.preventDefault();
            if (serialsDialog) {
                if (typeof serialsDialog.close === 'function') serialsDialog.close();
                else serialsDialog.removeAttribute('open');
            }
        };

        btnCloseSerialsX?.addEventListener('click', closeSerialsModal);
        btnSerialsCancel?.addEventListener('click', closeSerialsModal);
        serialsDialog?.addEventListener('click', (e) => {
            if (e.target === serialsDialog) closeSerialsModal(e);
        });
    }
    if (window.AppPage) window.AppPage.ready(initialize);
    else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
    else initialize();
})();
