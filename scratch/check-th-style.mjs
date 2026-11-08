import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage();
    await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded' });
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);

    await page.goto('http://127.0.0.1:8000/delivery/consignments', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);

    const thInfo = await page.evaluate(() => {
        const ths = document.querySelectorAll('#delivery-consignments-table thead th');
        const result = [];
        ths.forEach(th => {
            const cs = window.getComputedStyle(th);
            result.push({
                text: th.innerText.trim(),
                bg: cs.backgroundColor,
                color: cs.color,
                classes: th.className,
                tableClass: th.closest('table')?.className,
                parentContainer: th.closest('div')?.className
            });
        });
        
        // Also check if any sticky cloned header exists
        const stickyThs = document.querySelectorAll('.sticky-data-table-header th');
        const stickyResult = [];
        stickyThs.forEach(th => {
            const cs = window.getComputedStyle(th);
            stickyResult.push({
                text: th.innerText.trim(),
                bg: cs.backgroundColor,
                color: cs.color
            });
        });

        return { normalTh: result, stickyTh: stickyResult };
    });

    console.log('TH_INFO_JSON:' + JSON.stringify(thInfo));
    await browser.close();
})();
