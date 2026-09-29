(() => {
    // Turbo replaces the body without firing DOMContentLoaded again.
    // Page scripts use this hook on both a full load and an in-app visit.
    let lifecycle = new AbortController();
    window.AppPage = {
        ready(callback) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback, {once: true});
            } else {
                callback();
            }
        },
        get signal() { return lifecycle.signal; },
    };
    document.addEventListener('turbo:before-render', () => {
        lifecycle.abort();
        lifecycle = new AbortController();
    });
})();
