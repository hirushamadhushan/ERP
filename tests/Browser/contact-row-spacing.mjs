import { chromium } from '@playwright/test';
import fs from 'node:fs';
import assert from 'node:assert/strict';

// Exercise the real view styles with synthetic contacts; no app login or DB writes.
const layout = fs.readFileSync('resources/views/layouts/app.blade.php', 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
const contacts = fs.readFileSync('resources/views/contacts/index.blade.php', 'utf8').match(/<style>([\s\S]*?)<\/style>/)[1];
const values = ['MAN-CUS-001', 'Kasun Stores', 'Kasun Perera', 'kasun.customer@example.test', 'TEST-TIN-C001', 'Rs. 75,000.00', '30 days', 'Rs. 12,500.00', 'Rs. 0.00', '2026-10-19', 'Retail', 'No. 25, Main Street, Near Liberty Plaza, Colombo 03, Western Province, Sri Lanka, 00300', '0771234501', 'Rs. 0.00', 'Rs. 0.00', ...Array.from({ length: 10 }, (_, i) => `Custom field ${i + 1} sample value`)];
const escape = value => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;');
const row = () => `<tr><td><details class="contact-actions"><summary>Actions</summary><div style="position:absolute">View Edit Delete</div></details></td>${values.map((value, i) => `<td data-contact-column="${i === 11 ? 'address' : i >= 15 ? `custom_fields.${i - 15}` : 'other'}">${escape(value)}</td>`).join('')}</tr>`;
const browser = await chromium.launch({ channel: 'chrome', headless: true });
try {
    const page = await browser.newPage({ viewport: { width: 1365, height: 768 } });
    await page.setContent(`<style>${layout}\n${contacts}</style><main><div id="contacts-page" style="width:1030px"><div style="overflow-x:auto"><table id="contacts-table" class="dataTable" style="width:100%"><thead><tr>${['Action', ...values.map((_, i) => `Column ${i + 1}`)].map(value => `<th>${value}</th>`).join('')}</tr></thead><tbody>${row()}${row()}</tbody></table></div></div></main>`);
    const sizes = await page.locator('#contacts-table tbody tr').evaluateAll(rows => rows.map(row => row.getBoundingClientRect().height));
    console.log('Contact row heights:', sizes);
    assert.ok(sizes.every(height => height <= 85), `Rows should be compact, got ${sizes}`);
    assert.ok(await page.locator('#contacts-table').evaluate(table => table.scrollWidth > 1030), 'Wide columns should scroll horizontally instead of squeezing text vertically');
} finally {
    await browser.close();
}
