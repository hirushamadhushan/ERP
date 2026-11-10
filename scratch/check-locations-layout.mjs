import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1366, height: 768 } });
    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/home', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);

    await page.goto('http://127.0.0.1:8000/business-locations', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    const diag = await page.evaluate(() => {
        const len = document.querySelector('#locations-table_length');
        const flt = document.querySelector('#locations-table_filter');
        const reg = document.querySelector('.sticky-table-region');
        const hdr = document.querySelector('.sticky-data-table-header');
        return {
            len: { float: getComputedStyle(len).float, rect: len.getBoundingClientRect() },
            flt: { float: getComputedStyle(flt).float, rect: flt.getBoundingClientRect() },
            reg: { pos: getComputedStyle(reg).position, clear: getComputedStyle(reg).clear, rect: reg.getBoundingClientRect() },
            hdr: { pos: getComputedStyle(hdr).position, rect: hdr.getBoundingClientRect() },
        };
    });
    console.log(JSON.stringify(diag, null, 2));
    await browser.close();
})();

