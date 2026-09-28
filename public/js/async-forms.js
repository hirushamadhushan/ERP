(() => {
    'use strict';
    const pending = new WeakSet();
    const announce = message => {
        let toast = document.getElementById('async-form-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'async-form-toast';
            toast.setAttribute('role', 'status');
            toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;background:#7c3aed;color:white;padding:16px;border-radius:12px;box-shadow:0 8px 24px #0002';
            document.body.append(toast);
        }
        toast.textContent = message;
        toast.hidden = false;
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => toast.hidden = true, 4000);
    };
    function showErrors(form, error) {
        let box = form.querySelector('[data-async-errors]');
        if (!box) {
            box = document.createElement('div');
            box.dataset.asyncErrors = '';
            box.setAttribute('role', 'alert');
            box.style.cssText = 'margin:16px;padding:12px;color:#9f1239;background:#fff1f2;border-radius:8px';
            form.prepend(box);
        }
        box.textContent = error.message;
        box.tabIndex = -1;
        form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
        Object.keys(error.errors || {}).forEach(name => {
            const field = name.replace(/\.([^.]*)/g, '[$1]');
            const input = form.elements.namedItem(field);
            if (input instanceof HTMLElement) input.setAttribute('aria-invalid', 'true');
        });
        (form.querySelector('[aria-invalid=true]') || box).focus();
    }
    function throwIfAborted(signal) {
        if (signal?.aborted) throw new DOMException('Request superseded by a newer filter.', 'AbortError');
    }
    async function refresh(url = location.href, signal) {
        const response = await fetch(url, {headers: {Accept: 'text/html'}, cache: 'no-store', signal});
        throwIfAborted(signal);
        if (!response.ok || response.redirected) throw new Error('Changes were saved, but the list could not refresh. Reload to see the latest records.');
        const html = await response.text();
        throwIfAborted(signal);
        const doc = new DOMParser().parseFromString(html, 'text/html');
        // Keep table elements (and delegated handlers) alive when replacing their rows.
        document.querySelectorAll('table[data-async-table]').forEach(table => {
            throwIfAborted(signal);
            const fresh = doc.getElementById(table.id);
            if (!fresh) throw new Error('Changes saved. Reload to see the updated list.');
            if (window.jQuery?.fn?.dataTable?.isDataTable(table)) {
                const api = jQuery(table).DataTable();
                const rows = Array.from(fresh.tBodies[0].rows).filter(row => !row.querySelector('[colspan]'));
                api.clear().rows.add(rows).draw(false);
                if (api.page() >= api.page.info().pages) api.page('last').draw('page');
            } else table.tBodies[0].replaceChildren(...fresh.tBodies[0].children);
            if (table.tFoot && fresh.tFoot) table.tFoot.replaceChildren(...fresh.tFoot.children);
        });
        document.querySelectorAll('[data-async-options]').forEach(select => {
            throwIfAborted(signal);
            const fresh = doc.getElementById(select.id);
            if (fresh) select.replaceChildren(...fresh.children);
        });
        document.querySelectorAll('[data-async-region]').forEach(region => {
            throwIfAborted(signal);
            const fresh = doc.getElementById(region.id);
            if (fresh) region.replaceChildren(...fresh.children);
        });
        document.dispatchEvent(new CustomEvent('app:content-updated'));
        window.StickyDataTables?.update();
    }
    const filters = new WeakMap();
    async function filter(form) {
        filters.get(form)?.abort();
        const controller = new AbortController();
        filters.set(form, controller);
        const url = new URL(form.action || location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        form.setAttribute('aria-busy', 'true');
        document.dispatchEvent(new CustomEvent('app:request-start'));
        try {
            await refresh(url, controller.signal);
            if (filters.get(form) !== controller) return;
            history.replaceState(history.state, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') showErrors(form, error);
        } finally {
            if (filters.get(form) === controller) form.removeAttribute('aria-busy');
            document.dispatchEvent(new CustomEvent('app:request-end'));
        }
    }
    async function submit(form, submitter) {
        if (pending.has(form)) return;
        pending.add(form);
        const data = new FormData(form);
        if (submitter?.name) data.set(submitter.name, submitter.value);
        const buttons = Array.from(form.querySelectorAll('button[type=submit],button:not([type])'));
        const states = buttons.map(button => [button, button.disabled, button.innerHTML]);
        form.querySelector('[data-async-errors]')?.remove();
        buttons.forEach(button => {
            button.disabled = true;
            const spinner = document.createElement('i');
            spinner.className = 'bi bi-arrow-repeat animate-spin inline-block mr-2';
            spinner.setAttribute('aria-hidden', 'true');
            button.replaceChildren(spinner, document.createTextNode('Saving…'));
        });
        form.setAttribute('aria-busy', 'true');
        document.dispatchEvent(new CustomEvent('app:request-start'));
        try {
            const result = await AppErrors.request(form.action, {
                method: form.method.toUpperCase(), body: data,
                headers: {'X-Async-Form': '1', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}
            });
            form.closest('dialog')?.close();
            if (submitter?.hasAttribute('data-async-navigate')) {
                location.assign(result.redirect);
                return;
            }
            if (document.querySelector('[data-async-table], [data-async-options], [data-async-region]')) await refresh();
            announce(result.message);
        } catch (error) {
            if (form.closest('dialog')?.open || !form.closest('dialog')) showErrors(form, error);
            else AppErrors.show(error.message);
        } finally {
            states.forEach(([button, disabled, html]) => { button.disabled = disabled; button.innerHTML = html; });
            form.removeAttribute('aria-busy');
            pending.delete(form);
            document.dispatchEvent(new CustomEvent('app:request-end'));
        }
    }
    // Bubble after each page's delete confirmation; cancellation must remain respected.
    document.addEventListener('submit', event => {
        if (!event.defaultPrevented && event.target.matches('form[data-async-filter]')) {
            event.preventDefault();
            filter(event.target);
            return;
        }
        if (event.defaultPrevented || !event.target.matches('form[data-async-form]')) return;
        event.preventDefault();
        submit(event.target, event.submitter);
    });
    window.AsyncForms = {submit, refresh, filter, announce};
})();
