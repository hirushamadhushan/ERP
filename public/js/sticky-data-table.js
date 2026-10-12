(() => {
    const states = [];
    let listenersInstalled = false;
    let installTimer = null;
    let observer = null;
    // Removed tables must not remain referenced after an in-app page visit.
    document.addEventListener('turbo:before-render', () => {
        states.forEach(state => state.floating.remove());
        states.length = 0;
    });

    function verticalScrollContainer(element) {
        for (let parent = element.parentElement; parent; parent = parent.parentElement) {
            const overflowY = getComputedStyle(parent).overflowY;
            if (/(auto|scroll|overlay)/.test(overflowY)) return parent;
        }

        return null;
    }

    function update() {
        const navigation = document.querySelector('header.sticky');
        const navigationBottom = navigation?.getBoundingClientRect().bottom || 0;

        states.forEach(state => {
            if (state.region.closest('.hidden') || state.region.offsetWidth === 0) {
                state.floating.classList.remove('is-visible');
                return;
            }

            // CSS sticky offsets are measured from the nearest scrolling box.
            // In this layout <main> begins immediately below the navbar. Its top
            // padding must also be removed so the header touches the navbar.
            // This calculation still supports pages using the browser viewport.
            const scrollContainer = verticalScrollContainer(state.sticky);
            const scrollTop = scrollContainer?.getBoundingClientRect().top || 0;
            const scrollPaddingTop = scrollContainer
                ? Number.parseFloat(getComputedStyle(scrollContainer).paddingTop) || 0
                : 0;
            const stickyTop = navigationBottom - scrollTop - scrollPaddingTop;

            const sourceCells = Array.from(state.table.tHead.rows[0].cells);
            const cloneCells = Array.from(state.clone.tHead.rows[0].cells);
            sourceCells.forEach((cell, index) => {
                const width = cell.getBoundingClientRect().width;
                cloneCells[index].className = cell.className;
                cloneCells[index].style.display = getComputedStyle(cell).display;
                if (cell.hasAttribute('aria-sort')) {
                    cloneCells[index].setAttribute('aria-sort', cell.getAttribute('aria-sort'));
                } else {
                    cloneCells[index].removeAttribute('aria-sort');
                }
                cloneCells[index].style.width = `${width}px`;
                cloneCells[index].style.minWidth = `${width}px`;
                cloneCells[index].style.maxWidth = `${width}px`;
            });

            state.sticky.style.top = `${stickyTop}px`;
            state.sticky.style.width = `${state.viewport.clientWidth}px`;
            state.clone.style.setProperty('width', `${state.table.scrollWidth}px`, 'important');
            state.track.style.width = `${state.table.scrollWidth}px`;
            state.track.style.transform = `translateX(${-state.viewport.scrollLeft}px)`;

            const viewportRect = state.viewport.getBoundingClientRect();
            const containerRect = scrollContainer?.getBoundingClientRect();
            const visibleBottom = Math.min(window.innerHeight, containerRect?.bottom || window.innerHeight);
            const visibleTop = Math.max(navigationBottom, containerRect?.top || 0);
            const hasHorizontalOverflow = state.viewport.scrollWidth > state.viewport.clientWidth + 1;
            const tableCrossesViewport = viewportRect.top < visibleBottom && viewportRect.bottom > visibleTop;
            const nativeScrollbarIsBelowView = viewportRect.bottom > visibleBottom + 2;
            const showFloating = hasHorizontalOverflow && tableCrossesViewport && nativeScrollbarIsBelowView;
            state.floating.classList.toggle('is-visible', showFloating);
            if (showFloating) {
                state.floating.style.left = `${viewportRect.left}px`;
                state.floating.style.width = `${viewportRect.width}px`;
                state.floating.style.bottom = `${Math.max(0, window.innerHeight - visibleBottom)}px`;
                state.floatingSpacer.style.width = `${state.viewport.scrollWidth}px`;
                if (Math.abs(state.floating.scrollLeft - state.viewport.scrollLeft) > 1) {
                    state.floating.scrollLeft = state.viewport.scrollLeft;
                }
            }
        });
    }

    function installTable(table, dataTable = null) {
        if (!table?.tHead || !table.tBodies.length || table.dataset.stickyTableInstalled === 'true') return;
        table.dataset.stickyTableInstalled = 'true';

        const region = document.createElement('div');
        const viewport = document.createElement('div');
        const previousParent = table.parentElement;
        region.className = 'sticky-table-region';
        viewport.className = 'sticky-table-viewport';
        table.parentNode.insertBefore(region, table);
        region.appendChild(viewport);
        viewport.appendChild(table);
        if (previousParent?.matches('.overflow-x-auto, .sticky-table-host, .product-table-host')) {
            previousParent.classList.add('sticky-table-host-ready');
        }

        const sticky = document.createElement('div');
        const track = document.createElement('div');
        const clone = document.createElement('table');
        sticky.className = 'sticky-data-table-header';
        track.className = 'sticky-data-table-track';
        clone.className = table.className;
        clone.appendChild(table.tHead.cloneNode(true));
        clone.querySelectorAll('[id]').forEach(element => element.removeAttribute('id'));
        table.classList.add('sticky-data-table-source');
        track.appendChild(clone);
        sticky.appendChild(track);
        region.insertBefore(sticky, viewport);

        const floating = document.createElement('div');
        const floatingSpacer = document.createElement('div');
        floating.className = 'sticky-data-table-scrollbar';
        floating.setAttribute('aria-label', 'Scroll table horizontally');
        floatingSpacer.className = 'sticky-data-table-scrollbar-spacer';
        floating.appendChild(floatingSpacer);
        document.body.appendChild(floating);

        states.push({ table, region, viewport, sticky, track, clone, floating, floatingSpacer });
        viewport.addEventListener('scroll', () => {
            track.style.transform = `translateX(${-viewport.scrollLeft}px)`;
            if (Math.abs(floating.scrollLeft - viewport.scrollLeft) > 1) floating.scrollLeft = viewport.scrollLeft;
        }, { passive: true });
        floating.addEventListener('scroll', () => {
            if (Math.abs(viewport.scrollLeft - floating.scrollLeft) > 1) viewport.scrollLeft = floating.scrollLeft;
        }, { passive: true });
        sticky.addEventListener('click', event => {
            const cloneCell = event.target.closest('th');
            if (!cloneCell) return;

            const cellIndex = Array.from(cloneCell.parentElement.cells).indexOf(cloneCell);
            const sourceCell = table.tHead.rows[0].cells[cellIndex];
            const cloneControl = event.target.closest('input, button, select, a');
            if (cloneControl) {
                const cloneControls = Array.from(cloneCell.querySelectorAll('input, button, select, a'));
                const sourceControls = Array.from(sourceCell.querySelectorAll('input, button, select, a'));
                const sourceControl = sourceControls[cloneControls.indexOf(cloneControl)];
                if (!sourceControl) return;
                if ('checked' in cloneControl) sourceControl.checked = cloneControl.checked;
                if ('value' in cloneControl) sourceControl.value = cloneControl.value;
                sourceControl.dispatchEvent(new Event('change', { bubbles: true }));
                if (sourceControl.matches('button, a')) sourceControl.click();
                return;
            }

            sourceCell.click();
        });
        dataTable?.on('draw column-visibility column-sizing', () => requestAnimationFrame(update));

        if (!listenersInstalled) {
            window.addEventListener('scroll', update, { passive: true });
            document.addEventListener('scroll', event => {
                // The floating scrollbar's own scroll handler must run first so
                // it can drive the real table viewport. Recalculating here in
                // the capture phase would immediately snap its thumb back.
                if (event.target instanceof Element && event.target.classList.contains('sticky-data-table-scrollbar')) return;
                update();
            }, { passive: true, capture: true });
            window.addEventListener('resize', update);
            listenersInstalled = true;
        }
        requestAnimationFrame(update);
    }

    function install(dataTable) {
        installTable(dataTable.table().node(), dataTable);
    }

    function installElement(tableOrSelector) {
        const table = typeof tableOrSelector === 'string'
            ? document.querySelector(tableOrSelector)
            : tableOrSelector;
        if (table) installTable(table);
    }

    function shouldInstall(table) {
        if (!table.matches('main table') || !table.tHead || !table.tBodies.length) return false;
        if (table.dataset.noStickyTable === 'true') return false;
        if (table.closest('form, dialog, [role="dialog"], [data-no-sticky-table], .receipt-paper, .invoice-preview')) return false;
        return !table.matches('.product-pricing, .opening-stock-table, .product-detail-table, .receipt-table, .invoice-preview-table');
    }

    function dataTableFor(table) {
        if (!window.jQuery?.fn?.dataTable?.isDataTable(table)) return null;
        return window.jQuery(table).DataTable();
    }

    function installAll(root = document) {
        const tables = root.matches?.('table') ? [root] : Array.from(root.querySelectorAll?.('main table') || []);
        tables.filter(shouldInstall).forEach(table => installTable(table, dataTableFor(table)));
        requestAnimationFrame(update);
    }

    function scheduleInstall(root = document) {
        clearTimeout(installTimer);
        installTimer = setTimeout(() => installAll(root), 60);
    }

    function watchForTables() {
        observer?.disconnect();
        observer = new MutationObserver(mutations => {
            if (mutations.some(mutation => Array.from(mutation.addedNodes).some(node =>
                node instanceof Element && (node.matches('table') || node.querySelector('table'))
            ))) scheduleInstall();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    const boot = () => {
        scheduleInstall();
        watchForTables();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
    document.addEventListener('turbo:load', boot);
    document.addEventListener('app:content-updated', () => scheduleInstall());
    document.addEventListener('click', () => requestAnimationFrame(update), { passive: true });

    window.StickyDataTables = { install, installElement, installAll, update };
})();
