import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const page = await context.newPage();

    console.log('Navigating to login...');
    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2500);

    console.log('Navigating to consignments...');
    await page.goto('http://127.0.0.1:8000/delivery/consignments');
    await page.waitForTimeout(1000);

    // Capture top view
    await page.screenshot({ path: 'C:/Users/Admin/.gemini/antigravity/brain/8f5b98b3-4490-4719-8a5c-495d90f5fb85/delivery-mockup-top.png' });
    console.log('Saved top screenshot');

    // Scroll to deliveries table
    const table = page.locator('#delivery-consignments-table');
    if (await table.count() > 0) {
        await table.scrollIntoViewIfNeeded();
        await page.waitForTimeout(400);
        await page.screenshot({ path: 'C:/Users/Admin/.gemini/antigravity/brain/8f5b98b3-4490-4719-8a5c-495d90f5fb85/delivery-mockup-table.png' });
        console.log('Saved table screenshot');
    }

    await browser.close();
    console.log('ALL DONE');
})();
