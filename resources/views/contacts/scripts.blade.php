<script>
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('contact-dialog');
    const form = document.getElementById('contact-form');
    const fields = document.getElementById('contact-fields');
    const saveButton = document.getElementById('save-contact');
    const typeSelect = document.getElementById('contact-type');
    const more = document.getElementById('contact-more');
    const title = document.getElementById('contact-dialog-title');
    const pageType = @json($type);
    const storeUrl = @json(route('contacts.store'));
    const hasErrors = @json($errors->any());
    let previousFocus;

    function updateFields() {
        document.querySelectorAll('[data-contact-types]').forEach(section => {
            const visible = section.dataset.contactTypes.split(' ').includes(typeSelect.value);
            section.classList.toggle('hidden', !visible);
            section.querySelectorAll('input, select, textarea').forEach(input => input.disabled = !visible);
        });
        form.elements.business_name.required = form.elements.entity_type.value === 'business';
        form.elements.commission_percentage.required = typeSelect.value === 'commission';
    }

    function openDialog() {
        previousFocus = document.activeElement;
        updateFields();
        dialog.showModal();
    }

    function resetForm() {
        form.reset();
        fields.disabled = false;
        form.querySelectorAll('input:not([type="hidden"]), textarea').forEach(input => {
            if (input.type === 'radio') input.checked = input.value === 'individual';
            else input.value = '';
        });
        form.querySelectorAll('select').forEach(select => select.value = '');
        typeSelect.value = pageType;
        form.elements.status.value = 'active';
        ['opening_balance', 'opening_due_cans', 'commission_percentage'].forEach(key => form.elements[key].value = '0');
        form.elements._method.value = 'POST';
        form.elements._contact_id.value = '';
        form.action = storeUrl;
        more.open = false;
        saveButton.style.display = '';
        saveButton.disabled = false;
        saveButton.textContent = 'Save';
        document.getElementById('contact-validation-errors')?.remove();
    }

    document.getElementById('add-contact').addEventListener('click', () => {
        resetForm();
        title.textContent = pageType === 'commission' ? 'Add a commission agent' : 'Add a new contact';
        openDialog();
    });
    document.querySelectorAll('[data-close-contact]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => previousFocus?.focus());
    typeSelect.addEventListener('change', updateFields);
    form.querySelectorAll('[name="entity_type"]').forEach(input => input.addEventListener('change', updateFields));
    form.addEventListener('invalid', () => more.open = true, true);
    form.addEventListener('submit', () => {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving…';
    });

    document.getElementById('contacts-table').addEventListener('click', async event => {
        const button = event.target.closest('.contact-action');
        if (!button || button.disabled) return;
        button.disabled = true;
        const error = document.getElementById('contact-load-error');
        error.hidden = true;
        try {
            const response = await fetch(button.dataset.contactUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('Unable to load this contact. Refresh the page and try again.');
            const contact = await response.json();
            resetForm();
            Object.entries(contact).forEach(([key, value]) => {
                if (key === 'custom_fields') {
                    for (let i = 0; i < 10; i++) form.elements['custom_fields[' + i + ']'].value = value?.[i] ?? '';
                } else if (form.elements.namedItem(key)) {
                    form.elements.namedItem(key).value = value ?? '';
                }
            });
            const viewOnly = button.dataset.mode === 'view';
            form.action = button.dataset.contactUrl;
            form.elements._method.value = 'PUT';
            form.elements._contact_id.value = contact.id;
            title.textContent = viewOnly ? 'Contact details' : 'Edit contact';
            fields.disabled = viewOnly;
            saveButton.style.display = viewOnly ? 'none' : '';
            more.open = true;
            openDialog();
        } catch (exception) {
            error.textContent = exception.message;
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });
    document.getElementById('contacts-table').addEventListener('submit', event => {
        if (event.target.matches('.delete-contact') && !window.confirm('Delete this contact? This cannot be undone.')) event.preventDefault();
    });

    if (hasErrors) {
        title.textContent = form.elements._contact_id.value ? 'Edit contact' : 'Add a new contact';
        more.open = true;
        openDialog();
    }

    if (window.jQuery && $.fn.DataTable) {
        const exportOptions = {
            columns: function (index, data, node) { return index > 0 && $(node).is(':visible'); },
            format: {
                body: function (data) {
                    const text = $('<div>').html(data).text().trim();
                    return /^[=+\-@\t\r]/.test(text) ? "'" + text : text;
                }
            }
        };
        $('#contacts-table').DataTable({
            pageLength: 25,
            order: [],
            autoWidth: false,
            scrollX: true,
            dom: '<"flex flex-wrap items-center justify-between gap-4 mb-5"lBf>rt<"flex flex-wrap items-center justify-between gap-4 mt-4"ip>',
            columnDefs: [{ targets: 0, orderable: false, searchable: false }],
            buttons: [
                { extend: 'csvHtml5', text: '<i class="bi bi-filetype-csv"></i> Export to CSV', exportOptions },
                { extend: 'excelHtml5', text: '<i class="bi bi-file-earmark-excel"></i> Export to Excel', exportOptions },
                { extend: 'print', text: '<i class="bi bi-printer"></i> Print', exportOptions },
                { extend: 'colvis', text: '<i class="bi bi-eye"></i> Column visibility', columns: ':gt(0)' },
                { extend: 'pdfHtml5', text: '<i class="bi bi-file-earmark-pdf"></i> Export to PDF', orientation: 'landscape', pageSize: 'A3', exportOptions }
            ],
            language: { emptyTable: 'No contacts found. Add a contact to get started.', searchPlaceholder: 'Search contacts…' },
            footerCallback: function () {
                const api = this.api();
                api.columns().every(function () {
                    if (!this.footer()?.hasAttribute('data-total')) return;
                    const sum = api.column(this.index(), { search: 'applied' }).data().toArray().reduce((sum, value) => sum + (Number(String(value).replace(/[^0-9.-]/g, '')) || 0), 0);
                    this.footer().textContent = 'Rs. ' + sum.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                });
            }
        });
    }
});
</script>
