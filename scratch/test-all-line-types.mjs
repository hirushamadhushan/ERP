import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await (await browser.newContext({ viewport: { width: 1366, height: 768 } })).newPage();
    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForURL('http://127.0.0.1:8000/', { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(500);

    await page.goto('http://127.0.0.1:8000/invoices/create?order=3');
    await page.waitForSelector('.reference-lines-scroll');
    await page.waitForTimeout(600);

    // Scroll table to the right
    await page.evaluate(() => {
        const scroller = document.querySelector('.reference-lines-scroll');
        if (scroller) scroller.scrollLeft = scroller.scrollWidth;
    });
    await page.waitForTimeout(300);

    // Take screenshot scrolled right
    await page.screenshot({ path: 'scratch/order3-scrolled-right.png' });

    const info = await page.evaluate(() => {
        const head = document.querySelector('.odoo-line-head');
        const firstLine = document.querySelector('#invoice-lines .odoo-line');
        const scroll = document.querySelector('.reference-lines-scroll');
        const headSpans = [...head.querySelectorAll('span')].map(s => ({
            text: s.textContent,
            rect: s.getBoundingClientRect(),
            style: window.getComputedStyle(s).textAlign
        }));
        return {
            scroll: { scrollWidth: scroll.scrollWidth, clientWidth: scroll.clientWidth, offsetWidth: scroll.offsetWidth },
            head: { rect: head.getBoundingClientRect(), width: head.offsetWidth, style: window.getComputedStyle(head).gridTemplateColumns },
            line: { rect: firstLine.getBoundingClientRect(), width: firstLine.offsetWidth, style: window.getComputedStyle(firstLine).gridTemplateColumns },
            headSpans
        };
    });
    console.log(JSON.stringify(info, null, 2));
    await browser.close();
})();
