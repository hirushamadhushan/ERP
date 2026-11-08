import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';

(async () => {
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

    console.log('--- Test 1: Load Order 2 and Verify Rows, Columns and Locking ---');
    await page.goto('http://127.0.0.1:8000/invoices/create?order=2');
    await page.waitForSelector('.reference-lines-scroll');
    await page.waitForTimeout(600);

    // Verify 10 table headers matching photo
    const headers = await page.locator('.odoo-line-head span').allTextContents();
    console.log('Table Headers:', headers);
    assert.deepEqual(headers, ['Type', 'Code', 'Details', 'Sales Price', 'Discount (%)', 'Discount Amt.', 'Available Qty', 'Qty', 'Total', 'Action']);

    // Verify row titles and lot numbers
    const rows = page.locator('#invoice-lines .odoo-line');
    const rowCount = await rows.count();
    console.log('Row count:', rowCount);
    assert.equal(rowCount, 3);

    // Row 0: Stock item
    const r0Type = await rows.nth(0).locator('.reference-line-type').textContent();
    const r0Code = await rows.nth(0).locator('.reference-line-code').textContent();
    const r0Title = await rows.nth(0).locator('.line-details-title').textContent();
    console.log('Row 0:', { type: r0Type, code: r0Code, title: r0Title });
    assert.equal(r0Type.trim(), 'Stock');
    assert.ok(r0Code.length > 0, 'Row 0 must have a code');
    assert.ok(r0Title.includes('LOT-ING-2026-001'), 'Row 0 must include lot LOT-ING-2026-001');

    // Row 1: Stock item
    const r1Type = await rows.nth(1).locator('.reference-line-type').textContent();
    const r1Code = await rows.nth(1).locator('.reference-line-code').textContent();
    const r1Title = await rows.nth(1).locator('.line-details-title').textContent();
    console.log('Row 1:', { type: r1Type, code: r1Code, title: r1Title });
    assert.equal(r1Type.trim(), 'Stock');
    assert.ok(r1Code.length > 0, 'Row 1 must have a code');
    assert.ok(r1Title.includes('LOT-OC-2026-101'), 'Row 1 must include lot LOT-OC-2026-101');

    // Row 2: Service item
    const r2Type = await rows.nth(2).locator('.reference-line-type').textContent();
    const r2Code = await rows.nth(2).locator('.reference-line-code').textContent();
    const r2Title = await rows.nth(2).locator('.line-details-title').textContent();
    console.log('Row 2:', { type: r2Type, code: r2Code, title: r2Title });
    assert.equal(r2Type.trim(), 'Service');
    assert.ok(r2Code.length > 0, 'Row 2 must have a code');
    assert.ok(r2Title.includes('On-site Cable Testing'), 'Row 2 must show service name');

    // Verify NO pencil icon exists
    const pencilCount = await page.locator('.btn-change-item').count();
    console.log('Pencil icons count:', pencilCount);
    assert.equal(pencilCount, 0);

    // Test clicking Row 0 title: it should NOT open a select dropdown!
    await rows.nth(0).locator('.line-details-title').click();
    await page.waitForTimeout(200);
    const r0SelectVisible = await rows.nth(0).locator('.line-product').isVisible();
    console.log('Row 0 Select visible after click:', r0SelectVisible);
    assert.equal(r0SelectVisible, false, 'Product select must remain hidden after clicking title');

    // Test clicking Row 2 (service) title: it should NOT open dropdown!
    await rows.nth(2).locator('.line-details-title').click();
    await page.waitForTimeout(200);
    const r2SelectVisible = await rows.nth(2).locator('.line-product').isVisible();
    console.log('Row 2 Service select visible after click:', r2SelectVisible);
    assert.equal(r2SelectVisible, false, 'Service select must remain hidden after clicking title');

    await page.screenshot({ path: 'scratch/order-rows-locked-verified.png' });
    console.log('Saved scratch/order-rows-locked-verified.png');

    console.log('--- Test 2: Add Duplicate Product and Verify Modal ---');
    // Row 0 currently has Qty 2 of Product 15 with lot LOT-ING-2026-001
    const initialQty = await rows.nth(0).locator('.line-qty').inputValue();
    console.log('Initial Qty of Row 0:', initialQty);
    assert.equal(initialQty, '2');

    // Click "Add Item" button
    await page.getByRole('button', { name: 'Add Item', exact: true }).click();
    await page.waitForTimeout(300);

    // Find the newly added row (now at index 3 in .odoo-line)
    const newRow = page.locator('#invoice-lines .odoo-line').last();
    const newSelect = newRow.locator('.line-product');
    await newSelect.selectOption('15'); // Select Product 15
    await page.waitForTimeout(300);

    // Lot picker should appear
    const lotPicker = newRow.locator('.line-lot-picker');
    assert.ok(await lotPicker.isVisible(), 'Lot picker should appear for Product 15');

    // Select lot 8 (LOT-ING-2026-001) which is already in Row 0!
    await lotPicker.selectOption('8');
    await page.waitForTimeout(400);

    // The Duplicate Item Dialog must be visible!
    const dupDialog = page.locator('#duplicate-item-dialog');
    const isDupModalVisible = await dupDialog.isVisible();
    console.log('Duplicate modal visible:', isDupModalVisible);
    assert.ok(isDupModalVisible, 'Duplicate dialog must open');

    const modalTitle = await dupDialog.locator('.duplicate-item-title').textContent();
    const modalSubtitle = await dupDialog.locator('#dup-item-subtitle').textContent();
    const modalText = await dupDialog.locator('.duplicate-item-text').textContent();

    console.log('Modal Title:', modalTitle);
    console.log('Modal Subtitle:', modalSubtitle);
    console.log('Modal Text:', modalText);

    assert.equal(modalTitle.trim(), 'Already on this invoice');
    assert.ok(modalSubtitle.includes('LOT-ING-2026-001'));
    assert.ok(modalText.includes('Where should qty 1 go?'));

    // Take screenshot of duplicate modal (Photo 1 matching)
    await page.screenshot({ path: 'scratch/duplicate-modal-verified.png' });
    console.log('Saved scratch/duplicate-modal-verified.png');

    console.log('--- Test 3: Click "Add to paid line" ---');
    await page.locator('#btn-dup-paid').click();
    await page.waitForTimeout(300);

    assert.equal(await dupDialog.isVisible(), false, 'Modal should close');
    // Verify Row 0 quantity increased from 2 to 3!
    const updatedQty = await rows.nth(0).locator('.line-qty').inputValue();
    console.log('Updated Qty of Row 0:', updatedQty);
    assert.equal(updatedQty, '3', 'Row 0 quantity must be incremented to 3');

    // Verify row count returned to 3 (draft row was removed)
    assert.equal(await rows.count(), 3);

    console.log('--- Test 4: Add duplicate again and click "Add as Free Issue" ---');
    await page.getByRole('button', { name: 'Add Item', exact: true }).click();
    await page.waitForTimeout(300);

    const newRow2 = page.locator('#invoice-lines .odoo-line').last();
    await newRow2.locator('.line-product').selectOption('15');
    await page.waitForTimeout(300);
    await newRow2.locator('.line-lot-picker').selectOption('8');
    await page.waitForTimeout(400);

    assert.ok(await dupDialog.isVisible(), 'Duplicate dialog must open again');
    await page.locator('#btn-dup-free').click();
    await page.waitForTimeout(300);

    assert.equal(await dupDialog.isVisible(), false, 'Modal should close');

    // Verify a 4th line was added as Free Issue
    const newRowCount = await rows.count();
    console.log('New row count after Free Issue:', newRowCount);
    assert.equal(newRowCount, 4);

    const freeRow = rows.nth(3);
    const freePrice = await freeRow.locator('.line-price').inputValue();
    const freeTotal = await freeRow.locator('.line-amount').textContent();
    const freeBadge = await freeRow.locator('.badge-free-issue').textContent();

    console.log('Free Issue Price:', freePrice);
    console.log('Free Issue Line Total:', freeTotal);
    console.log('Free Issue Badge:', freeBadge);

    assert.equal(parseFloat(freePrice), 0, 'Free issue price must be 0');
    assert.ok(freeTotal.includes('0.00'), 'Free issue total must be 0.00');
    assert.equal(freeBadge.trim(), 'Free Issue');

    await page.screenshot({ path: 'scratch/free-issue-line-verified.png' });
    console.log('Saved scratch/free-issue-line-verified.png');

    console.log('ALL TESTS PASSED SUCCESSFULLY!');
    await browser.close();
})();

