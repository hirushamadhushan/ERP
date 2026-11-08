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

// Go to /invoices/create
await page.goto('http://127.0.0.1:8000/invoices/create');
await page.waitForSelector('.odoo-line-head');

// Print headers
const headers = await page.locator('.odoo-line-head span').allTextContents();
console.log('Headers:', headers);

// Screenshot the table
await page.locator('.reference-lines-scroll').screenshot({ path: 'scratch/invoice-table-after.png' });
console.log('Saved scratch/invoice-table-after.png');

await browser.close();
