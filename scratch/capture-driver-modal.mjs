import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 650 } });
    const page = await context.newPage();

    console.log('Logging in...');
    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2500);

    // 1. Capture Drivers Modal
    console.log('Going to delivery drivers...');
    await page.goto('http://127.0.0.1:8000/delivery/drivers');
    await page.waitForTimeout(1000);

    console.log('Clicking Add Driver...');
    await page.click('#add-driver');
    await page.waitForTimeout(500);

    await page.screenshot({ path: 'C:/Users/Admin/.gemini/antigravity/brain/8f5b98b3-4490-4719-8a5c-495d90f5fb85/driver-modal-pinned.png' });
    console.log('Captured driver-modal-pinned.png');

    // Also test scrolling inside modal body
    await page.locator('.delivery-dialog-body').evaluate(el => el.scrollTop = 300);
    await page.waitForTimeout(300);
    await page.screenshot({ path: 'C:/Users/Admin/.gemini/antigravity/brain/8f5b98b3-4490-4719-8a5c-495d90f5fb85/driver-modal-scrolled.png' });
    console.log('Captured driver-modal-scrolled.png');

    // 2. Capture Vehicles Modal
    console.log('Going to delivery vehicles...');
    await page.goto('http://127.0.0.1:8000/delivery/vehicles');
    await page.waitForTimeout(1000);

    console.log('Clicking Add Vehicle...');
    await page.click('#add-vehicle');
    await page.waitForTimeout(500);

    await page.screenshot({ path: 'C:/Users/Admin/.gemini/antigravity/brain/8f5b98b3-4490-4719-8a5c-495d90f5fb85/vehicle-modal-pinned.png' });
    console.log('Captured vehicle-modal-pinned.png');

    await browser.close();
    console.log('ALL DONE');
})();
