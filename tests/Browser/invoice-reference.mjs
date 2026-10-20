import { chromium } from '@playwright/test';
import fs from 'node:fs';
import assert from 'node:assert/strict';

// Exercise the production layout script/styles without writing business records.
const view = fs.readFileSync('resources/views/invoices/form.blade.php', 'utf8');
const styles = view.match(/<style>([\s\S]*?)<\/style>/)[1]
    + fs.readFileSync('public/css/invoice-reference.css', 'utf8');
const field = (name, value = '') => `<div class="odoo-field"><label>${name}</label><input name="${name}" value="${value}"></div>`;
const row = `<div class="odoo-line"><select class="line-product"><option value="">Select</option><option value="1">Stock</option><option value="2">Service</option></select><select class="line-lot"></select><input class="line-qty"><input class="line-price"><select class="line-tax"></select><b class="line-amount">0</b><button class="line-remove">Delete</button></div>`;
const browser = await chromium.launch({ channel: 'chrome', headless: true });
try {
    const page = await browser.newPage({ viewport: { width: 1365, height: 768 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent(`<style>*{box-sizing:border-box}.hidden{display:none!important}body{margin:0;padding:80px 24px 24px 90px;background:#f4f4fa}${styles}</style>
      <div class="odoo-invoice-shell"><div class="invoice-workspace">
      <div class="invoice-main"><div class="odoo-sheet">
        <div class="reference-invoice-heading"><span>Invoice No #:</span><strong>INV/2026/0006</strong><button id="reference-orders">ORDERS</button></div>
        <div class="odoo-header-grid">${['customer_id','journal','invoice_date'].map(name => field(name)).join('')}
          <div class="odoo-field odoo-due-field has-payment-term"><label id="invoice-due-label">Payment terms</label><input id="invoice-due-date" name="due_date"><span class="payment-term-control"><input id="invoice-payment-term" name="payment_term" value="30 Days"><button class="payment-term-open">Edit</button></span></div>
        </div>
        <div class="odoo-tabs"><button data-tab="lines">Lines</button><button data-tab="journal">Journal</button><button data-tab="other">Other</button></div>
        <div id="tab-lines" class="odoo-tab-panel"><div class="reference-order-summary">Order</div><div class="odoo-line-head"></div><div id="invoice-lines"></div>
          <div class="odoo-add-row"><button data-add="product">Add</button><button data-add="section">Section</button><button data-add="note">Note</button><button id="open-catalog">Catalog</button></div>
          <div class="odoo-bottom"><textarea name="terms"></textarea><div class="odoo-totals">${['Total','Taxes','Net Total'].map(name => `<div>${name}<b>0.00</b></div>`).join('')}</div></div>
        </div>
        <div id="tab-journal" class="odoo-tab-panel hidden">Ledger content</div>
        <div id="tab-other" class="odoo-tab-panel hidden">${['customer_reference','salesperson_id','location_id','payment_reference'].map(name => field(name)).join('')}</div>
        <div class="reference-invoice-footer"><button id="reference-ledger">Preview Ledger</button><button class="reference-save">Save</button><a>Close</a></div>
      </div></div><aside class="invoice-side"><section class="reference-customer-card"><h2>Customer Details</h2><dl><dt>Name</dt><dd>—</dd></dl></section></aside>
      </div></div><dialog id="orders-dialog">Orders</dialog>
      <script type="application/json" id="reference-product-metadata">{"1":{"code":"STK","stock":true},"2":{"code":"SVC","stock":false}}</script>`);
    await page.evaluate(row => {
        document.querySelector('[data-add="product"]').onclick = () => document.getElementById('invoice-lines').insertAdjacentHTML('beforeend', row);
        document.querySelectorAll('[data-tab]').forEach(button => button.onclick = () => document.querySelectorAll('.odoo-tab-panel').forEach(panel => panel.classList.toggle('hidden', panel.id !== `tab-${button.dataset.tab}`)));
    }, row);
    await page.addScriptTag({ path: 'public/js/invoice-reference.js' });
    assert.deepEqual(await page.locator('.odoo-line-head span').allTextContents(),
        ['Type','Code','Details','Sales Price','Discount (%)','Discount Amt.','Available Qty','Qty','Total','Lot','Taxes','Action']);
    assert.equal(await page.locator('#invoice-due-date').isVisible(), true);
    assert.equal(await page.locator('.reference-order-summary').isVisible(), false);
    const customer = await page.locator('[name="customer_id"]').boundingBox();
    const date = await page.locator('[name="invoice_date"]').boundingBox();
    assert.ok(date.x > customer.x && Math.abs(date.y - customer.y) < 2, 'Header should have two aligned columns');
    await page.getByRole('button', { name: 'Add Service', exact: true }).click();
    assert.equal(await page.locator('.line-product option').count(), 2, 'Service picker excludes stock products');
    await page.locator('.line-product').selectOption('2');
    assert.equal(await page.locator('.reference-line-type').textContent(), 'Service');
    assert.equal(await page.locator('.reference-line-code').textContent(), 'SVC');
    const calculate = view.slice(view.indexOf(' function calc(){'), view.indexOf("\n host.addEventListener('input',calc)"));
    await page.evaluate(calculate => {
        const host = document.getElementById('invoice-lines');
        host.firstElementChild.insertAdjacentHTML('beforeend', '<input class="line-discount" value="10"><span class="line-discount-amount"></span>');
        host.querySelector('.line-qty').value = '2';
        host.querySelector('.line-price').value = '100';
        host.querySelector('.line-tax').innerHTML = '<option data-rate="18">18%</option>';
        document.body.insertAdjacentHTML('beforeend', '<input id="tax-display" value="exclusive"><b id="untaxed-total"></b><b id="tax-total"></b><b id="grand-total"></b><b id="amount-due"></b>');
        new Function('host', 'invoiceLocked', 'renderDraftJournal', 'updateCatalogTotal', calculate + ';calc();')(host, false, () => {}, () => {});
    }, calculate);
    assert.equal(await page.locator('.line-discount-amount').textContent(), '20.00 Rs');
    assert.equal(await page.locator('#grand-total').textContent(), '212.40 Rs');
    assert.ok(await page.locator('.reference-lines-scroll').evaluate(el => el.scrollWidth > el.clientWidth));
    await page.getByRole('button', { name: 'Preview Ledger' }).click();
    assert.equal(await page.locator('.reference-ledger-dialog').isVisible(), true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#tab-lines').isVisible(), true);
    assert.equal(await page.locator('.reference-invoice-footer').evaluate(el => !!el.closest('.reference-form-scroll')), false);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.addStyleTag({content:'body{padding:20px}'});
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= 390), 'Mobile layout must not overflow the page');
    assert.deepEqual(errors, []);
    console.log('Invoice reference layout, services, scrolling, ledger and mobile checks passed.');
} finally {
    await browser.close();
}
