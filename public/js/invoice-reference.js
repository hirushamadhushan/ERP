(() => {
    function initialize() {
        const shell = document.querySelector('.odoo-invoice-shell');
        if (!shell || shell.dataset.referenceReady) return;
        shell.dataset.referenceReady = '1';
        const header = shell.querySelector('.odoo-header-grid');
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
        const journal = header.querySelector('[name="journal"]');
        if (journal) journal.closest('.odoo-field').querySelector('label').textContent = 'Inv Type';
        const location = header.querySelector('[name="location_id"]');
        if (location) location.closest('.odoo-field').querySelector('label').textContent = 'Stock Type';
        const ref = header.querySelector('[name="customer_reference"]');
        if (ref) ref.closest('.odoo-field').querySelector('label').textContent = 'Reference';
        const add = shell.querySelector('[data-add="product"]');
        if (add) add.textContent = 'Add Item';
        const note = shell.querySelector('[data-add="note"]');
        if (note) note.textContent = 'Add Text';
        const section = shell.querySelector('[data-add="section"]');
        if (section) section.hidden = true;
        const catalogButton = document.getElementById('open-catalog');
        if (catalogButton) catalogButton.textContent = 'Browse Products';
        // Keep the table's horizontal scrolling separate from the notes and totals.
        const tableHead = shell.querySelector('.odoo-line-head');
        const lines = document.getElementById('invoice-lines');
        const tableScroll = document.createElement('div');
        tableScroll.className = 'reference-lines-scroll';
        tableHead.before(tableScroll);
        tableScroll.append(tableHead, lines);
        tableHead.replaceChildren(...['Type', 'Code', 'Details', 'Sales Price', 'Discount (%)', 'Discount Amt.', 'Available Qty', 'Qty', 'Total', 'Lot', 'Taxes', 'Action'].map(text => {
            const label = document.createElement('span');
            label.textContent = text;
            return label;
        }));
        const products = JSON.parse(document.getElementById('reference-product-metadata')?.textContent || '{}');
        const lots = JSON.parse(document.getElementById('reference-lot-metadata')?.textContent || '{}');
        function decorateRows() {
            lines.querySelectorAll('.odoo-line').forEach(row => {
                if (!row.querySelector('.reference-line-type')) {
                    const type = document.createElement('span');
                    type.className = 'reference-line-type';
                    const code = document.createElement('span');
                    code.className = 'reference-line-code';
                    row.prepend(type, code);
                    const available = document.createElement('span');
                    available.className = 'reference-line-available';
                    row.append(available);
                }
                const product = products[row.querySelector('.line-product').value];
                row.querySelector('.reference-line-type').textContent = product ? (product.stock ? 'Stock' : 'Service') : '—';
                row.querySelector('.reference-line-code').textContent = product?.code || '—';
                const productId = row.querySelector('.line-product').value;
                const locationId = location?.value || '';
                const lotId = row.querySelector('.line-lot').value;
                const quantities = product?.lots || lotId ? (lots[productId] || []) : (product?.quantities || []);
                const available = quantities.filter(stock => (!locationId || String(stock.location_id) === locationId) && (!lotId || String(stock.id) === lotId))
                    .reduce((sum, stock) => sum + Number(stock.available), 0);
                row.querySelector('.reference-line-available').textContent = product?.stock ? available.toLocaleString(undefined, {maximumFractionDigits:4}) : '—';
            });
        }
        new MutationObserver(decorateRows).observe(lines, {childList:true});
        lines.addEventListener('change', decorateRows);
        location?.addEventListener('change', decorateRows);
        decorateRows();
        const service = document.createElement('button');
        service.type = 'button';
        service.textContent = 'Add Service';
        service.disabled = shell.hasAttribute('data-invoice-locked');
        service.addEventListener('click', () => {
            add.click();
            const select = lines.lastElementChild?.querySelector('.line-product');
            if (!select) return;
            [...select.options].forEach(option => {
                if (option.value && products[option.value]?.stock) option.remove();
            });
            select.focus();
        });
        add.after(service);
        const bottom = shell.querySelector('.odoo-bottom');
        const notes = document.createElement('div');
        notes.className = 'reference-notes';
        bottom.prepend(notes);
        const moveNote = (name, title) => {
            const input = shell.querySelector(`[name="${name}"]`);
            if (!input) return;
            const oldField = input.closest('.odoo-field');
            const label = document.createElement('label');
            label.append(document.createTextNode(title), input);
            notes.append(label);
            oldField?.remove();
        };
        moveNote('payment_reference', 'Remark');
        moveNote('terms', 'Terms & Conditions');
        const termLabel = document.createElement('label');
        termLabel.textContent = 'Payment Terms';
        const termControl = shell.querySelector('.payment-term-control');
        if (termControl) termLabel.append(termControl);
        notes.append(termLabel);
        const dueLabel = document.getElementById('invoice-due-label');
        const resetDueLabel = () => { dueLabel.textContent = 'Due Date'; };
        resetDueLabel();
        document.getElementById('invoice-payment-term')?.addEventListener('input', resetDueLabel);
        document.getElementById('invoice-payment-term')?.addEventListener('change', resetDueLabel);
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
        // Always return to the item editor when the ledger preview is dismissed.
        const ledgerPanel = document.getElementById('tab-journal');
        const ledgerDialog = document.createElement('dialog');
        ledgerDialog.className = 'invoice-flow-dialog reference-ledger-dialog';
        const closeLedger = document.createElement('button');
        closeLedger.type = 'button';
        closeLedger.textContent = 'Close Ledger';
        closeLedger.addEventListener('click', () => {
            ledgerDialog.close();
            shell.querySelector('[data-tab="lines"]').click();
        });
        ledgerDialog.append(closeLedger);
        ledgerDialog.addEventListener('close', () => shell.querySelector('[data-tab="lines"]').click());
        document.getElementById('tab-lines').after(ledgerDialog);
        document.getElementById('reference-orders')?.addEventListener('click', () => document.getElementById('orders-dialog').showModal());
        shell.querySelector('[data-tab="journal"]').addEventListener('click', () => {
            document.getElementById('tab-lines').classList.remove('hidden');
            ledgerDialog.append(ledgerPanel);
            if (!ledgerDialog.open) ledgerDialog.showModal();
        });
        document.getElementById('reference-ledger')?.addEventListener('click', () => shell.querySelector('[data-tab="journal"]').click());
        const table = document.querySelector('#orders-dialog table');
        if (table && window.jQuery?.fn.DataTable) {
            jQuery(table).DataTable({pageLength:10, order:[[0,'desc']], columnDefs:[{targets:5,orderable:false}], language:{search:'Search:'}});
        }
        const dialog = document.getElementById('orders-dialog');
        dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    }
    if (window.AppPage) window.AppPage.ready(initialize);
    else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
    else initialize();
})();
