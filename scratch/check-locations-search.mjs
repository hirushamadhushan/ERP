import { chromium } from '@playwright/test';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const page = await context.newPage();

    page.on('pageerror', e => console.log('PAGEERROR:', e.message));
    page.on('console', m => { if (m.type() === 'error') console.log('CONSOLE ERROR:', m.text()); });

    await page.goto('http://127.0.0.1:8000/login');
    await page.fill('input[wire\\:model="username"]', 'user1');
    await page.fill('input[wire\\:model="password"]', '123456');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/home', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);

    await page.goto('http://127.0.0.1:8000/business-locations', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);

    const info = await page.evaluate(() => {
        const input = document.querySelector('#locations-table_filter input, .dataTables_filter input');
        const select = document.querySelector('#locations-table_length select');
        const ir = input.getBoundingClientRect();
        const sr = select.getBoundingClientRect();
        const topInput = document.elementFromPoint(ir.left + ir.width / 2, ir.top + ir.height / 2);
        const topSelect = document.elementFromPoint(sr.left + sr.width / 2, sr.top + sr.height / 2);
        return {
            topElSameAsInput: topInput === input,
            topElSameAsSelect: topSelect === select,
            rowsBefore: document.querySelectorAll('#locations-table tbody tr').length,
        };
    });
    console.log('HIT_TEST:', JSON.stringify(info));

    const inputLoc = page.locator('#locations-table_filter input, .dataTables_filter input');
    const box = await inputLoc.boundingBox();
    await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
    await page.keyboard.type('Galle');
    await page.waitForTimeout(500);
    const valAfterMouse = await inputLoc.inputValue();
    const rowsAfterMouse = await page.locator('#locations-table tbody tr').count();
    console.log('AFTER_MOUSE_SEARCH:', JSON.stringify({ valAfterMouse, rowsAfterMouse }));

    await page.screenshot({ path: 'C:/Users/Admin/.gemini/antigravity/brain/8f5b98b3-4490-4719-8a5c-495d90f5fb85/locations-search-fixed.png' });
    await browser.close();
})();
