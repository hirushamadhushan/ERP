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

    // Test 1: /users modal before and after #user-switcher
    await page.goto('http://127.0.0.1:8000/users', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.view-full-user-btn', { timeout: 10000 });
    await page.locator('.view-full-user-btn').first().click();
    await page.waitForSelector('#fullUserModal [data-user-tab="documents"]', { timeout: 10000 });
    await page.waitForTimeout(400);

    const optionCount = await page.locator('#user-switcher option').count();
    console.log('user-switcher options:', optionCount);
    let directViewUrl = null;
    if (optionCount > 1) {
        const secondVal = await page.locator('#user-switcher option').nth(1).getAttribute('value');
        directViewUrl = secondVal;
        await page.selectOption('#user-switcher', secondVal);
        await page.waitForSelector('#fullUserModal [data-user-tab="documents"]', { timeout: 10000 });
        await page.waitForTimeout(400);

        await page.click('#fullUserModal [data-user-tab="documents"]');
        await page.waitForTimeout(200);
        console.log('Docs visible AFTER switcher:', await page.locator('#fullUserModal [data-user-panel="documents"]').isVisible());

        await page.click('#fullUserModal #open-note-modal');
        await page.waitForTimeout(200);
        console.log('Note modal visible AFTER switcher:', await page.locator('#fullUserModal #note-modal').isVisible());
        await page.click('#fullUserModal #note-modal [data-close-note]');
        await page.waitForTimeout(200);

        await page.click('#fullUserModal [data-user-tab="activities"]');
        await page.waitForTimeout(200);
        console.log('Activities visible AFTER switcher:', await page.locator('#fullUserModal [data-user-panel="activities"]').isVisible());

        // Test Edit User -> Cancel returns to switched user (not first user)
        const switchedName = await page.locator('#fullUserModal aside h2').textContent();
        await page.click('#fullUserModal #open-user-editor');
        await page.waitForSelector('#fullUserModal #create-user-form', { timeout: 10000 });
        await page.click('#fullUserModal #create-user-form a:has-text("Cancel")');
        await page.waitForSelector('#fullUserModal aside h2', { timeout: 10000 });
        const returnedName = await page.locator('#fullUserModal aside h2').textContent();
        console.log('Edit->Cancel preserved switched user:', switchedName === returnedName, switchedName);
    }

    // Test 2: direct /users/{id}/view URL
    if (directViewUrl) {
        await page.goto(directViewUrl, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);
        await page.click('[data-user-tab="documents"]');
        await page.waitForTimeout(200);
        console.log('Direct view docs visible:', await page.locator('[data-user-panel="documents"]').isVisible());
        await page.click('[data-user-tab="activities"]');
        await page.waitForTimeout(200);
        console.log('Direct view activities visible:', await page.locator('[data-user-panel="activities"]').isVisible());
    }

    await browser.close();
})();
