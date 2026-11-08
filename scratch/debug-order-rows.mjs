import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
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

    const rowsInfo = await page.evaluate(() => {
        const rows = document.querySelectorAll('#invoice-lines .odoo-line, #invoice-lines .odoo-text-line');
        return Array.from(rows).map((row, idx) => {
            const isText = row.classList.contains('odoo-text-line');
            const select = row.querySelector('.line-product');
            const serviceBox = row.querySelector('.service-autocomplete-box');
            const confirmed = row.querySelector('.line-details-confirmed');
            const lotPickerWrap = row.querySelector('.line-lot-picker-wrap');
            const desc = row.querySelector('.line-description')?.value;
            const price = row.querySelector('.line-price')?.value;
            const lineType = row.querySelector('[data-name="line_type"]')?.value;
            const serviceId = row.querySelector('[data-name="service_id"]')?.value;

            return {
                idx,
                isText,
                kind: row.dataset.kind,
                targetKind: row.dataset.targetKind,
                rowStatus: row.dataset.rowStatus,
                lineType,
                serviceId,
                desc,
                price,
                select: select ? {
                    visible: !select.classList.contains('hidden'),
                    value: select.value,
                    optionsCount: select.options.length,
                    selectedText: select.options[select.selectedIndex]?.text
                } : null,
                serviceBox: serviceBox ? {
                    visible: !serviceBox.classList.contains('hidden')
                } : null,
                confirmed: confirmed ? {
                    visible: !confirmed.classList.contains('hidden'),
                    title: confirmed.querySelector('.line-details-title')?.textContent
                } : null,
                lotPickerWrap: lotPickerWrap ? {
                    visible: !lotPickerWrap.classList.contains('hidden')
                } : null
            };
        });
    });

    console.log(JSON.stringify(rowsInfo, null, 2));

    await browser.close();
})();

