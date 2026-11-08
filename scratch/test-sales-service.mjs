import { chromium } from '@playwright/test';

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
const page = await context.newPage();

// Login
await page.goto('http://127.0.0.1:8000/login');
await page.waitForSelector('input[wire\\:model="username"]');
await page.fill('input[wire\\:model="username"]', 'user1');
await page.fill('input[wire\\:model="password"]', '123456');
await page.click('button[type="submit"]');

await page.waitForURL('http://127.0.0.1:8000/', { timeout: 10000 }).catch(() => {});

// Go to /sales/create
await page.goto('http://127.0.0.1:8000/sales/create');
await page.waitForSelector('#btn-add-service');

console.log('--- Initial Row ---');
const initialSelect = page.locator('#items-tbody select').first();
console.log('First select class:', await initialSelect.getAttribute('class'));

// Click Add Service
console.log('--- Clicking Add Service ---');
await page.click('#btn-add-service');
await page.waitForTimeout(300);

const serviceSelect = page.locator('#items-tbody .service-picker').first();
console.log('Service picker select exists:', await serviceSelect.count());

const options = await serviceSelect.locator('option').allTextContents();
console.log('Options count:', options.length);
console.log('Options list:', options);

// Select option 1 (first registered service)
await serviceSelect.selectOption({ index: 1 });
await page.waitForTimeout(300);

const priceVal = await page.locator('#items-tbody .line-price').first().inputValue();
const totalVal = await page.locator('#items-tbody .line-total').first().inputValue();
const netTotalText = await page.locator('#order-net-total-display').inputValue();

console.log('Selected service price:', priceVal);
console.log('Line total:', totalVal);
console.log('Order net total:', netTotalText);

// Take screenshot
await page.screenshot({ path: 'scratch/sales-create-service-selected.png' });
console.log('Screenshot saved to scratch/sales-create-service-selected.png');

await browser.close();
