(() => {
    const states = [];
    let listenersInstalled = false;

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
            if (state.region.closest('.hidden') || state.region.offsetWidth === 0) return;

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
        });
    }

    function installTable(table, dataTable = null) {
        if (table.dataset.stickyTableInstalled === 'true') return;
        table.dataset.stickyTableInstalled = 'true';

        const region = document.createElement('div');
        const viewport = document.createElement('div');
        region.className = 'sticky-table-region';
        viewport.className = 'sticky-table-viewport';
        table.parentNode.insertBefore(region, table);
        region.appendChild(viewport);
        viewport.appendChild(table);

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

        states.push({ table, region, viewport, sticky, track, clone });
        viewport.addEventListener('scroll', () => {
            track.style.transform = `translateX(${-viewport.scrollLeft}px)`;
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

    window.StickyDataTables = { install, installElement, update };
})();
