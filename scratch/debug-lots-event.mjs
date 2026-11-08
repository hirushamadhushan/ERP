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

    await page.goto('http://127.0.0.1:8000/invoices/create?order=2');
    await page.waitForSelector('.reference-lines-scroll');
    await page.waitForTimeout(500);

    const debugLots = await page.evaluate(() => {
        const loc = document.querySelector('[name="location_id"]')?.value;
        const lotsData = JSON.parse(document.getElementById('reference-lot-metadata')?.textContent || '{}');
        const prodsData = JSON.parse(document.getElementById('reference-product-metadata')?.textContent || '{}');
        return {
            loc,
            prod15Lots: lotsData['15'],
            prod15Meta: prodsData['15'],
            allLotKeys: Object.keys(lotsData)
        };
    });

    console.log(JSON.stringify(debugLots, null, 2));

    await page.getByRole('button', { name: 'Add Item', exact: true }).click();
    await page.waitForTimeout(300);

    const lastRowInfo = await page.evaluate(() => {
        const rows = document.querySelectorAll('#invoice-lines .odoo-line');
        const last = rows[rows.length - 1];
        const pSel = last.querySelector('.line-product');
        pSel.value = '15';
        pSel.dispatchEvent(new Event('change', { bubbles: true }));

        const lotWrap = last.querySelector('.line-lot-picker-wrap');
        const lotSelect = last.querySelector('.line-lot-picker');
        const confirmed = last.querySelector('.line-details-confirmed');
        const dupDialog = document.getElementById('duplicate-item-dialog');

        return {
            pSelValue: pSel.value,
            lotWrapClass: lotWrap?.className,
            lotOptionsCount: lotSelect?.options.length,
            lotOptions: lotSelect ? Array.from(lotSelect.options).map(o => ({ value: o.value, text: o.text })) : [],
            confirmedClass: confirmed?.className,
            dupDialogOpen: dupDialog ? dupDialog.open : false
        };
    });

    console.log(JSON.stringify(lastRowInfo, null, 2));

    await browser.close();
})();

