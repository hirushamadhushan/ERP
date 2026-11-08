<?php

// 1. Update public/css/invoice-reference.css
$cssPath = 'public/css/invoice-reference.css';
$css = file_get_contents($cssPath);

// Replace grid-template-columns and header styling
$oldGridBlock = '.odoo-invoice-shell .reference-lines-scroll .odoo-line-head,
.odoo-invoice-shell .reference-lines-scroll .odoo-line {
    display: grid !important;
    grid-template-columns: minmax(180px, 2.6fr) 105px 85px 100px 85px 75px 105px 44px !important;
    padding: 10px 14px;
    gap: 8px;
    align-items: center;
}
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%) !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.06em !important;
    border: none !important;
    border-radius: 12px 12px 0 0 !important;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.2) !important;
}
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span {
    color: #ffffff !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15) !important;
}
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:last-child {
    text-align: center;
}';

$newGridBlock = '.odoo-invoice-shell .reference-lines-scroll .odoo-line-head,
.odoo-invoice-shell .reference-lines-scroll .odoo-line {
    display: grid !important;
    grid-template-columns: minmax(180px, 2.5fr) 105px 85px 75px 85px 100px 105px 36px !important;
    padding: 10px 14px;
    gap: 8px;
    align-items: center;
}
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head {
    background: #1e1b4b !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: none !important;
    letter-spacing: 0.01em !important;
    border: none !important;
    border-radius: 4px 4px 0 0 !important;
    box-shadow: none !important;
}
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span {
    color: #ffffff !important;
    font-weight: 700 !important;
    text-shadow: none !important;
}
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:nth-child(1) { text-align: left !important; }
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:nth-child(2) { text-align: right !important; }
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:nth-child(3) { text-align: right !important; }
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:nth-child(4) { text-align: right !important; }
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:nth-child(5) { text-align: right !important; }
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:nth-child(6) { text-align: right !important; }
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:nth-child(7) { text-align: right !important; }
.odoo-invoice-shell .reference-lines-scroll .odoo-line-head span:nth-child(8) { text-align: center !important; }';

// Normalize CRLF
$css = str_replace(
    str_replace("\r\n", "\n", $oldGridBlock),
    str_replace("\r\n", "\n", $newGridBlock),
    str_replace("\r\n", "\n", $css)
);

// Update explicit column assignments to: Details (1), Sales Price (2), Available Qty (3), Qty (4), Discount % (5), Discount Amt (6), Total (7), Remove (8)
$oldCols = '.reference-lines-scroll .line-details-cell { grid-column: 1 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-product { grid-column: 1 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-price { grid-column: 2 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-discount { grid-column: 3 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-discount-amount { grid-column: 4 !important; grid-row: 1 !important; }
.reference-lines-scroll .reference-line-available { grid-column: 5 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-qty { grid-column: 6 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-amount { grid-column: 7 !important; grid-row: 1 !important; }';

$newCols = '.reference-lines-scroll .line-details-cell { grid-column: 1 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-product { grid-column: 1 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-price { grid-column: 2 !important; grid-row: 1 !important; }
.reference-lines-scroll .reference-line-available { grid-column: 3 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-qty { grid-column: 4 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-discount { grid-column: 5 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-discount-amount { grid-column: 6 !important; grid-row: 1 !important; }
.reference-lines-scroll .line-amount { grid-column: 7 !important; grid-row: 1 !important; }';

$css = str_replace(
    str_replace("\r\n", "\n", $oldCols),
    str_replace("\r\n", "\n", $newCols),
    $css
);

// Update table scroll container style
$tableScrollBlock = '
.odoo-invoice-shell .reference-lines-scroll {
    border: 1px solid #e2e8f0 !important;
    border-radius: 4px !important;
    background: #ffffff !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04) !important;
    min-height: 280px !important;
    overflow-x: auto !important;
    margin-bottom: 16px !important;
}
.odoo-invoice-shell .reference-lines-scroll .odoo-line {
    border-bottom: 1px solid #f1f5f9 !important;
    transition: background 0.15s ease !important;
}
.odoo-invoice-shell .reference-lines-scroll .odoo-line:hover {
    background: rgba(248, 250, 252, 0.7) !important;
}
';

// Replace inputs styling
$oldInputs = '.odoo-invoice-shell .reference-lines-scroll .line-price,
.odoo-invoice-shell .reference-lines-scroll .line-qty,
.odoo-invoice-shell .reference-lines-scroll .line-discount {
    min-height: 36px !important;
    height: 36px !important;
    background: #ffffff !important;
    border: 1.5px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 6px 10px !important;
    font-size: 12.5px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    text-align: right !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02) !important;
    transition: all 0.2s ease !important;
}';

$newInputs = '.odoo-invoice-shell .reference-lines-scroll .line-price,
.odoo-invoice-shell .reference-lines-scroll .line-qty,
.odoo-invoice-shell .reference-lines-scroll .line-discount {
    min-height: 32px !important;
    height: 32px !important;
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 4px !important;
    padding: 4px 8px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #1e293b !important;
    text-align: right !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02) !important;
    transition: all 0.15s ease !important;
}';

$css = str_replace(
    str_replace("\r\n", "\n", $oldInputs),
    str_replace("\r\n", "\n", $newInputs),
    $css
);

// Update available qty, discount amount, line-amount and remove trash icon
$oldAvailable = '/* Available Qty */
.odoo-invoice-shell .reference-lines-scroll .reference-line-available {
    display: inline-flex !important;
    justify-content: center !important;
    align-items: center !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    color: #64748b !important;
    background: transparent !important;
}

/* Discount Amount & Total */
.odoo-invoice-shell .reference-lines-scroll .line-discount-amount {
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #64748b !important;
    text-align: right !important;
}
.odoo-invoice-shell .reference-lines-scroll .line-amount {
    font-size: 12px !important;
    font-weight: 800 !important;
    color: #0f172a !important;
    text-align: right !important;
}

/* Action Trash Icon - Matching Photo 2 (Blue trash can) */
.odoo-invoice-shell .reference-lines-scroll .line-remove,
.odoo-invoice-shell .reference-lines-scroll .line-remove button {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 32px !important;
    height: 32px !important;
    border: none !important;
    background: transparent !important;
    color: #0284c7 !important;
    font-size: 16px !important;
    cursor: pointer !important;
    border-radius: 8px !important;
    transition: all 0.15s ease !important;
}
.odoo-invoice-shell .reference-lines-scroll .line-remove:hover,
.odoo-invoice-shell .reference-lines-scroll .line-remove button:hover {
    color: #ef4444 !important;
    background: #fee2e2 !important;
}';

$newAvailable = '/* Available Qty */
.odoo-invoice-shell .reference-lines-scroll .reference-line-available {
    display: inline-flex !important;
    justify-content: flex-end !important;
    align-items: center !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #64748b !important;
    padding-right: 4px !important;
    background: transparent !important;
}

/* Discount Amount & Total */
.odoo-invoice-shell .reference-lines-scroll .line-discount-amount {
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    text-align: right !important;
    padding-right: 4px !important;
}
.odoo-invoice-shell .reference-lines-scroll .line-amount {
    font-size: 12.5px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    text-align: right !important;
    padding-right: 4px !important;
}

/* Action Trash Icon - Matching Orders Design */
.odoo-invoice-shell .reference-lines-scroll .line-remove,
.odoo-invoice-shell .reference-lines-scroll .line-remove button {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 28px !important;
    height: 28px !important;
    border: none !important;
    background: transparent !important;
    color: #cbd5e1 !important;
    font-size: 14px !important;
    cursor: pointer !important;
    border-radius: 4px !important;
    transition: all 0.15s ease !important;
}
.odoo-invoice-shell .reference-lines-scroll .line-remove:hover,
.odoo-invoice-shell .reference-lines-scroll .line-remove button:hover {
    color: #f43f5e !important;
    background: #fff1f2 !important;
}';

$css = str_replace(
    str_replace("\r\n", "\n", $oldAvailable),
    str_replace("\r\n", "\n", $newAvailable),
    $css
);

// Update product select
$oldProdSelect = '.line-details-cell .line-product {
    width: 220px !important;
    max-width: 240px !important;
    min-width: 180px !important;
    height: 36px !important;
    min-height: 36px !important;
    border-radius: 20px !important;
    border: 1.5px solid #93c5fd !important;
    padding: 6px 36px 6px 16px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    background-color: #ffffff !important;
    color: #1e293b !important;
    box-shadow: 0 0 0 3px rgba(147, 197, 253, 0.25) !important;
    outline: none !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}';

$newProdSelect = '.line-details-cell .line-product {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    height: 32px !important;
    min-height: 32px !important;
    border-radius: 4px !important;
    border: 1px solid #e2e8f0 !important;
    padding: 4px 10px !important;
    font-size: 12px !important;
    font-weight: 500 !important;
    background-color: #ffffff !important;
    color: #1e293b !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02) !important;
    outline: none !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}';

$css = str_replace(
    str_replace("\r\n", "\n", $oldProdSelect),
    str_replace("\r\n", "\n", $newProdSelect),
    $css
);

// Append tableScrollBlock
$css .= $tableScrollBlock;

file_put_contents($cssPath, $css);
echo "Successfully updated $cssPath\n";

// 2. Update public/js/invoice-reference.js
$jsPath = 'public/js/invoice-reference.js';
$js = file_get_contents($jsPath);

$oldJsHeaders = "tableHead.replaceChildren(...['Details', 'Sales Price', 'Discount (%)', 'Discount Amt.', 'Available Qty', 'Qty', 'Total', 'A'].map(text => {";
$newJsHeaders = "tableHead.replaceChildren(...['Details', 'Sales Price', 'Available Qty', 'Qty', 'Discount (%)', 'Discount Amt.', 'Total', ''].map(text => {";

$js = str_replace($oldJsHeaders, $newJsHeaders, $js);
file_put_contents($jsPath, $js);
echo "Successfully updated $jsPath\n";

// 3. Update resources/views/invoices/form.blade.php
$formPath = 'resources/views/invoices/form.blade.php';
$form = file_get_contents($formPath);

$oldFormHeaders = '<div class="odoo-line-head"><span>Details</span><span>Sales Price</span><span>Discount (%)</span><span>Discount Amt.</span><span>Available Qty</span><span>Qty</span><span>Total</span><span>A</span></div>';
$newFormHeaders = '<div class="odoo-line-head"><span>Details</span><span>Sales Price</span><span>Available Qty</span><span>Qty</span><span>Discount (%)</span><span>Discount Amt.</span><span>Total</span><span></span></div>';

$form = str_replace($oldFormHeaders, $newFormHeaders, $form);
file_put_contents($formPath, $form);
echo "Successfully updated $formPath\n";

// 4. Update tests/Browser/invoice-reference.mjs
$testPath = 'tests/Browser/invoice-reference.mjs';
if (file_exists($testPath)) {
    $test = file_get_contents($testPath);
    $oldTestHeaders = "['Details','Sales Price','Discount (%)','Discount Amt.','Available Qty','Qty','Total','A']";
    $newTestHeaders = "['Details','Sales Price','Available Qty','Qty','Discount (%)','Discount Amt.','Total','']";
    $test = str_replace($oldTestHeaders, $newTestHeaders, $test);
    file_put_contents($testPath, $test);
    echo "Successfully updated $testPath\n";
}

