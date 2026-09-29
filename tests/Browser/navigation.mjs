import { chromium } from '@playwright/test';
import fs from 'node:fs';
import assert from 'node:assert/strict';
// Use a private cookie exported from an authorized local test session.
// This smoke test changes input values but never submits business records.
assert.ok(process.env.NAVIGATION_COOKIE_FILE, 'Set NAVIGATION_COOKIE_FILE to a private test-session cookie JSON file.');
const browser = await chromium.launch({channel:'chrome',headless:true});
try {
 const context = await browser.newContext();
 await context.addCookies([JSON.parse(fs.readFileSync(process.env.NAVIGATION_COOKIE_FILE))]);
 const page=await context.newPage(); const errors=[]; let documents=0;
 page.on('pageerror',e=>errors.push(e.message));
 page.on('request',r=>{if(r.isNavigationRequest() && r.frame()===page.mainFrame())documents++;});
 await page.goto('http://127.0.0.1:8000/products/serial-numbers',{waitUntil:'networkidle'});
 for(const label of ['Add Product','Units','Serial Numbers','Units','List Products','Add Product','Categories','Brands','Variations','Warranties','Serial Numbers']) {
   const start=Date.now();
   await page.locator('header a').getByText(label,{exact:true}).click();
   await page.waitForFunction(()=>!document.documentElement.hasAttribute('aria-busy'));
   await page.waitForTimeout(150);
   console.log(label,Date.now()-start,'ms',new URL(page.url()).pathname);
   if(label==='Add Product') {
     await page.locator('#purchase_price').fill('100');
     await page.locator('#margin').fill('25');
     assert.equal(Number(await page.locator('#selling_price').inputValue()),125);
     assert.ok(await page.locator('.tox-tinymce').count());
     page.once('dialog',d=>d.accept());
   }
   if(label==='Units') {
     await page.locator('#add-unit').click();
     assert.ok(await page.locator('#unit-dialog').evaluate(e=>e.open));
     await page.locator('#unit-dialog [data-close-unit]').first().click();
     assert.ok(await page.locator('#units-table_wrapper').count());
   }
 }
 for(let repeat=0;repeat<2;repeat++) {
   await page.locator('#main-sidebar a').filter({hasText:'Settings'}).click();
   await page.locator('#business-settings-form').waitFor();
   await page.waitForFunction(()=>!document.documentElement.hasAttribute('aria-busy'));
   await page.locator('#main-sidebar a').filter({hasText:'Products'}).click();
   await page.locator('.action-toggle').first().waitFor();
   await page.waitForFunction(()=>!document.documentElement.hasAttribute('aria-busy'));
   await page.locator('.action-toggle').first().click();
   assert.equal(await page.locator('.action-menu:visible').count(),1);
   await page.locator('h1').first().click();
 }
 console.log('JS_ERRORS',errors); console.log('FULL_DOCUMENT_REQUESTS',documents);
 assert.deepEqual(errors,[]); assert.equal(documents,1);
} finally { await browser.close(); }
