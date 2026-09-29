import './bootstrap';
import * as Turbo from '@hotwired/turbo';

// Forms already own their AJAX, validation and upload handling.
Turbo.config.forms.mode = 'off';
Turbo.setProgressBarDelay(100);
// Speculative requests congest the single-worker local PHP server.
document.addEventListener('turbo:before-prefetch', event => event.preventDefault());
// Cloned snapshots contain initialized editors/tables; always render fresh HTML.
Turbo.cache.exemptPageFromCache();
document.addEventListener('turbo:load', () => Turbo.cache.exemptPageFromCache());
document.addEventListener('turbo:before-render', () => window.tinymce?.remove());
