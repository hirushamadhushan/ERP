import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({
        viewport: { width: 1366, height: 768 }
    });
    const page = await context.newPage();

    // Login
    await page.goto('http://127.0.0.1:8000/login');
    await page.waitForSelector('input[wire\\:model="username"]');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForURL('http://127.0.0.1:8000/', { timeout: 10000 }).catch(() => {});

    // Go to invoice create with order 2
    await page.goto('http://127.0.0.1:8000/invoices/create?order=2');
    await page.waitForSelector('.reference-lines-scroll');
    await page.waitForTimeout(500);

    const info = await page.evaluate(() => {
        const scroll = document.querySelector('.reference-lines-scroll');
        const head = document.querySelector('.odoo-line-head');
        const lines = document.querySelector('#invoice-lines');
        const line = document.querySelector('.odoo-line');
        const textLine = document.querySelector('.odoo-text-line');
        const sheet = document.querySelector('.odoo-sheet');
        const main = document.querySelector('.invoice-main');
        const workspace = document.querySelector('.invoice-workspace');

        return {
            workspace: workspace ? { offsetWidth: workspace.offsetWidth, clientWidth: workspace.clientWidth } : null,
            main: main ? { offsetWidth: main.offsetWidth, clientWidth: main.clientWidth } : null,
            sheet: sheet ? { offsetWidth: sheet.offsetWidth, clientWidth: sheet.clientWidth } : null,
            scroll: {
                clientWidth: scroll.clientWidth,
                scrollWidth: scroll.scrollWidth,
                offsetWidth: scroll.offsetWidth,
                computedWidth: window.getComputedStyle(scroll).width,
                computedMaxWidth: window.getComputedStyle(scroll).maxWidth,
            },
            head: head ? {
                offsetWidth: head.offsetWidth,
                scrollWidth: head.scrollWidth,
                computedWidth: window.getComputedStyle(head).width,
                computedMinWidth: window.getComputedStyle(head).minWidth,
                gridCols: window.getComputedStyle(head).gridTemplateColumns,
            } : null,
            lines: lines ? {
                offsetWidth: lines.offsetWidth,
                scrollWidth: lines.scrollWidth,
                computedWidth: window.getComputedStyle(lines).width,
                computedMinWidth: window.getComputedStyle(lines).minWidth,
            } : null,
            line: line ? {
                offsetWidth: line.offsetWidth,
                scrollWidth: line.scrollWidth,
                computedWidth: window.getComputedStyle(line).width,
                computedMinWidth: window.getComputedStyle(line).minWidth,
                gridCols: window.getComputedStyle(line).gridTemplateColumns,
            } : null,
            textLine: textLine ? {
                offsetWidth: textLine.offsetWidth,
                scrollWidth: textLine.scrollWidth,
                computedWidth: window.getComputedStyle(textLine).width,
                computedMinWidth: window.getComputedStyle(textLine).minWidth,
            } : null,
        };
    });

    console.log(JSON.stringify(info, null, 2));

    // Scroll right
    await page.evaluate(() => {
        const scroll = document.querySelector('.reference-lines-scroll');
        if (scroll) scroll.scrollLeft = scroll.scrollWidth;
    });

    await page.screenshot({ path: 'scratch/table_scrolled_right.png', fullPage: false });
    console.log('Saved scratch/table_scrolled_right.png');

    await browser.close();
})();
