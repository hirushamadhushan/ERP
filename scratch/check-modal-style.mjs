import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const page = await context.newPage();

    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/home');

    await page.goto('http://127.0.0.1:8000/delivery/drivers');
    await page.click('#add-driver');

    const styles = await page.evaluate(() => {
        const header = document.querySelector('.delivery-dialog-header');
        const footer = document.querySelector('.delivery-dialog-footer');
        const dialog = document.querySelector('#driver-dialog');
        const hStyle = window.getComputedStyle(header);
        const fStyle = window.getComputedStyle(footer);
        const dStyle = window.getComputedStyle(dialog);
        return {
            headerBg: hStyle.backgroundColor,
            headerBorderBottom: hStyle.borderBottom,
            footerBg: fStyle.backgroundColor,
            footerBorderTop: fStyle.borderTop,
            dialogMaxHeight: dStyle.maxHeight,
            dialogOverflow: dStyle.overflow
        };
    });

    console.log('STYLES:', JSON.stringify(styles, null, 2));
    await browser.close();
})();

