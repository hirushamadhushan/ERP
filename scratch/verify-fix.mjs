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

    // Initial screenshot of table
    await page.locator('.reference-lines-scroll').screenshot({ path: 'scratch/table_fixed_normal.png' });

    // Scroll table to the right
    await page.evaluate(() => {
        const scroll = document.querySelector('.reference-lines-scroll');
        if (scroll) scroll.scrollLeft = scroll.scrollWidth;
    });
    await page.waitForTimeout(200);

    // Scrolled screenshot of table
    await page.locator('.reference-lines-scroll').screenshot({ path: 'scratch/table_fixed_scrolled_right.png' });
    await page.screenshot({ path: 'scratch/page_fixed_view.png' });

    console.log('Saved screenshots successfully!');
    await browser.close();
})();

