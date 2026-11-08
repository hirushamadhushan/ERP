import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const page = await context.newPage();

    await page.goto('http://127.0.0.1:8000/login');
    await page.waitForSelector('input[wire\\:model="username"]');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForURL('http://127.0.0.1:8000/', { timeout: 10000 }).catch(() => {});

    await page.goto('http://127.0.0.1:8000/invoices/create');
    await page.waitForSelector('.reference-lines-scroll');
    await page.waitForTimeout(600);

    // Scroll .reference-lines-scroll to right
    await page.evaluate(() => {
        const el = document.querySelector('.reference-lines-scroll');
        if (el) el.scrollLeft = el.scrollWidth;
    });
    await page.waitForTimeout(300);
    await page.screenshot({ path: 'C:/Users/Admin/.gemini/antigravity/brain/8f5b98b3-4490-4719-8a5c-495d90f5fb85/action-header-create-scrolled.png' });

    await browser.close();
    console.log('Scrolled screenshot saved successfully');
})();
