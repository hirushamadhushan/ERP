(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-auto-filter]').forEach(function (form) {
            let timer;

            function submit() {
                if (form.hasAttribute('data-async-filter')) {
                    window.AsyncForms.filter(form);
                    return;
                }
                if (form.dataset.submitting === 'true') return;
                if (form.dataset.clientFilter === 'true') {
                    form.dispatchEvent(new CustomEvent('clientfilter'));
                    return;
                }
                form.dataset.submitting = 'true';
                form.setAttribute('aria-busy', 'true');
                form.style.opacity = '0.65';
                form.submit();
            }

            form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach(function (control) {
                control.addEventListener('change', submit);
            });

            form.querySelectorAll('input[data-auto-filter-search]').forEach(function (input) {
                input.addEventListener('input', function () {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(submit, 450);
                });
            });
        });
    });
})();
