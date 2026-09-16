(() => {
    const messages = {
        0: 'Could not reach the server. Check your connection and check the records before submitting again.',
        401: 'Your session has ended. Sign in again before continuing.',
        403: 'You do not have permission to perform this action.',
        404: 'This record or page no longer exists.',
        413: 'The file exceeds the server upload limit. Choose a smaller file.',
        419: 'Your session expired. Keep a copy of your changes, then reload and sign in again.',
        429: 'Too many requests. Please wait a moment before trying again.'
    };
    const message = (status, data) => {
        const text = data?.message || messages[status] || 'The request could not be completed. Check the records before trying again.';
        return text + (data?.reference ? ' Error reference: ' + data.reference : '');
    };
    function show(text) {
        let box = document.getElementById('request-error');
        if (!box) {
            box = document.createElement('div');
            box.id = 'request-error';
            box.setAttribute('role', 'alert');
            box.style.cssText = 'position:fixed;bottom:20px;right:20px;max-width:min(480px,calc(100vw - 40px));padding:16px;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;border-radius:12px;z-index:99999;box-shadow:0 8px 30px #0002';
            const content = document.createElement('span');
            content.className = 'request-error-message';
            const close = document.createElement('button');
            close.type = 'button'; close.textContent = 'Dismiss';
            close.style.cssText = 'display:block;margin-top:8px;font-weight:bold';
            close.addEventListener('click', () => box.remove());
            box.append(content, close);
            (document.querySelector('dialog[open]') || document.body).appendChild(box);
        }
        box.querySelector('.request-error-message').textContent = text;
    }
    async function request(url, options = {}) {
        let response;
        try {
            response = await fetch(url, {...options, headers: {...options.headers, Accept: 'application/json'}});
        } catch {
            throw new Error(messages[0]);
        }
        let data;
        try { data = await response.json(); } catch {
            throw new Error(message(response.redirected ? 401 : response.status));
        }
        if (!response.ok) {
            const error = new Error(response.status === 422 && data.errors
                ? Object.values(data.errors).flat().join(' ')
                : message(response.status, data));
            error.status = response.status; error.errors = data.errors || {};
            throw error;
        }
        return data;
    }
    window.AppErrors = {message, show, request};
    if (window.jQuery) {
        // Pages keep their own field-level 422 rendering.
        jQuery(document).ajaxError((_event, xhr, settings, thrown) => {
            if (thrown === 'abort' || typeof settings.error === 'function') return;
            if (xhr.status === 422) {
                show(Object.values(xhr.responseJSON?.errors || {}).flat().join(' ') || 'Please check the form fields.');
                return;
            }
            show(message(xhr.status, xhr.responseJSON));
        });
        if (jQuery.fn.dataTable) jQuery.fn.dataTable.ext.errMode = 'none';
    }
})();
