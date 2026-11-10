import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const page = await context.newPage();

    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2500);

    await page.goto('http://127.0.0.1:8000/delivery/store');
    await page.waitForTimeout(1000);

    const styles = await page.evaluate(() => {
        const loading = document.querySelector('a.bg-emerald-600');
        const unloading = document.querySelector('a[href*="/delivery/unloading"]');
        const showStock = document.querySelector('form button');
        return {
            loading: loading ? { text: loading.innerText, br: getComputedStyle(loading).borderRadius, h: getComputedStyle(loading).height, pad: getComputedStyle(loading).padding } : null,
            unloading: unloading ? { text: unloading.innerText, br: getComputedStyle(unloading).borderRadius, h: getComputedStyle(unloading).height, pad: getComputedStyle(unloading).padding } : null,
            showStock: showStock ? { text: showStock.innerText, br: getComputedStyle(showStock).borderRadius, h: getComputedStyle(showStock).height, pad: getComputedStyle(showStock).padding } : null,
        };
    });

    console.log(JSON.stringify(styles, null, 2));
    await page.screenshot({ path: 'C:/Users/Admin/.gemini/antigravity/brain/8f5b98b3-4490-4719-8a5c-495d90f5fb85/store-buttons-after.png' });
    await browser.close();
})();
