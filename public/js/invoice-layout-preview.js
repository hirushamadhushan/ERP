(() => {
    const initialize = () => {
        const form = document.getElementById('invoice-layout-form');
        const dialog = document.getElementById('invoice-layout-preview-dialog');
        const trigger = document.getElementById('preview-invoice-layout');
        if (!form || !dialog || !trigger || trigger.dataset.previewReady) return;
        trigger.dataset.previewReady = '1';

        const paper = document.getElementById('invoice-preview-paper');
        const logo = document.getElementById('invoice-preview-logo');
        const savedLogo = logo.getAttribute('src');
        let temporaryLogo = null;

        const value = name => form.elements.namedItem(name)?.value?.trim() || '';
        const label = (key, fallback) => value(`labels[${key}]`) || fallback;
        const enabled = key => Boolean(form.querySelector(`input[type="checkbox"][name="options[${key}]"]`)?.checked);
        const setText = (id, text) => { document.getElementById(id).textContent = text; };
        const editorText = id => {
            const editor = window.tinymce?.get(id);
            return editor ? editor.getContent({ format: 'text' }).trim() : value(id);
        };

        const update = () => {
            paper.dataset.design = value('design') || 'classic';
            setText('invoice-preview-document-heading', `${label('invoice_heading', 'Invoice')} ${label('heading_not_paid', 'Unpaid')}`);
            const fields = {
                'invoice-preview-number-label': ['invoice_no_label', 'Invoice No.'],
                'invoice-preview-date-label': ['date_label', 'Date'],
                'invoice-preview-due-date-label': ['due_date_label', 'Due Date'],
                'invoice-preview-customer-label': ['customer_label', 'Customer'],
                'invoice-preview-client-id-label': ['client_id_label', 'Client ID'],
                'invoice-preview-sales-person-label': ['sales_person_label', 'Sales Person'],
                'invoice-preview-commission-agent-label': ['commission_agent_label', 'Commission Agent'],
                'invoice-preview-product-label': ['product_label', 'Product'],
                'invoice-preview-quantity-label': ['quantity_label', 'Quantity'],
                'invoice-preview-unit-price-label': ['unit_price_label', 'Unit Price'],
                'invoice-preview-subtotal-label': ['subtotal_label', 'Subtotal'],
                'invoice-preview-hsn-label': ['category_hsn_label', 'HSN'],
                'invoice-preview-subtotal-total-label': ['subtotal_total_label', 'Subtotal'],
                'invoice-preview-discount-label': ['discount_total_label', 'Discount'],
                'invoice-preview-tax-label': ['tax_label', 'Tax'],
                'invoice-preview-total-label': ['total_label', 'Total'],
                'invoice-preview-paid-label': ['amount_paid_label', 'Total paid'],
                'invoice-preview-due-label': ['total_due_label', 'Due'],
            };
            for (const [id, [key, fallback]] of Object.entries(fields)) setText(id, label(key, fallback));
            setText('invoice-preview-header-text', editorText('header_text'));
            setText('invoice-preview-footer-text', editorText('footer_text'));

            const subheadings = document.getElementById('invoice-preview-subheadings');
            subheadings.replaceChildren();
            for (let index = 1; index <= 5; index++) {
                const heading = value(`labels[sub_heading_${index}]`);
                if (!heading) continue;
                const line = document.createElement('div');
                line.textContent = heading;
                subheadings.append(line);
            }

            document.querySelectorAll('[data-preview-option]').forEach(element => {
                element.hidden = !enabled(element.dataset.previewOption);
            });
            document.querySelector('.invoice-preview-customer').hidden = !enabled('show_customer_info');
            document.getElementById('invoice-preview-business').querySelector('strong').hidden = !enabled('show_business_name');
            document.getElementById('invoice-preview-location').hidden = !enabled('show_location_name');

            if (temporaryLogo) URL.revokeObjectURL(temporaryLogo);
            temporaryLogo = null;
            const file = form.elements.namedItem('logo')?.files?.[0];
            if (enabled('show_logo') && file && file.type.startsWith('image/')) {
                temporaryLogo = URL.createObjectURL(file);
                logo.src = temporaryLogo;
                logo.hidden = false;
            } else {
                logo.src = savedLogo || '';
                logo.hidden = !enabled('show_logo') || !savedLogo;
            }
        };

        trigger.addEventListener('click', () => { update(); dialog.showModal(); });
        document.getElementById('close-invoice-layout-preview').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
        dialog.addEventListener('close', () => {
            if (temporaryLogo) URL.revokeObjectURL(temporaryLogo);
            temporaryLogo = null;
        });
    };

    if (window.AppPage?.ready) AppPage.ready(initialize);
    else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
