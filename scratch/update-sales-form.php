<?php
$filePath = 'resources/views/sales-orders/form.blade.php';
$content = file_get_contents($filePath);

// 1. Replace template id="service-row-tpl"
$pattern1 = '/<template id="service-row-tpl">[\s\S]*?<\/td>/';
$replace1 = '<template id="service-row-tpl">
    <tr class="item-row group hover:bg-slate-50/50 transition">
        <td class="py-2.5 px-3">
            <select name="lines[IDX][service_id]" class="service-picker w-full rounded border border-purple-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-purple-600 focus:ring-1 focus:ring-purple-600 shadow-2xs" style="border-radius: 4px;">
                <option value="">Select Service</option>
                @foreach($servicesJson as $s)
                    <option value="{{ $s[\'id\'] }}" data-price="{{ $s[\'price\'] }}" data-name="{{ $s[\'name\'] }}" data-code="{{ $s[\'code\'] }}">
                        {{ $s[\'name\'] }} @if(!empty($s[\'code\']))[{{ $s[\'code\'] }}]@endif
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="lines[IDX][description]" class="line-desc" value="">
            <input type="hidden" name="lines[IDX][item_type]" value="service">
        </td>';

if (!preg_match($pattern1, $content)) {
    die("Error: pattern1 not found\n");
}
$content = preg_replace($pattern1, $replace1, $content, 1);

// 2. Remove setupServiceAutocomplete function
$pattern2 = '/function setupServiceAutocomplete\(row, syncDiscountFn\) \{[\s\S]*?\n    \}/';
if (!preg_match($pattern2, $content)) {
    die("Error: pattern2 not found\n");
}
$content = preg_replace($pattern2, '', $content, 1);

// 3. Remove "setupServiceAutocomplete(row, syncDiscountOnSubtotalChange);" from attachRowListeners
$content = str_replace('setupServiceAutocomplete(row, syncDiscountOnSubtotalChange);', '', $content);

// 4. Add serviceSelect listener right before "if (productSelect) {"
$serviceSelectHandler = 'const serviceSelect = row.querySelector(\'.service-picker\');
        if (serviceSelect) {
            serviceSelect.addEventListener(\'change\', e => {
                const opt = e.target.selectedOptions[0];
                const descInput = row.querySelector(\'.line-desc\');
                if (!opt || !opt.value) {
                    if (descInput) descInput.value = \'\';
                    if (priceInput) priceInput.value = \'0.00\';
                    syncDiscountOnSubtotalChange();
                    recalculate();
                    return;
                }
                const price = opt.dataset.price || \'0.00\';
                const name = opt.dataset.name || opt.text;

                if (priceInput) priceInput.value = parseFloat(price).toFixed(2);
                if (descInput) descInput.value = name;

                syncDiscountOnSubtotalChange();
                recalculate();
            });
        }

        if (productSelect) {';

$content = str_replace('if (productSelect) {', $serviceSelectHandler, $content);

// 5. Update isRowEmpty for type === 'service'
$oldIsRowEmpty = 'if (type === \'service\') {
            const descInput = row.querySelector(\'.line-desc\');
            const priceInput = row.querySelector(\'.line-price\');
            const discAmtInput = row.querySelector(\'.line-disc-amt\');
            const discPctInput = row.querySelector(\'.line-disc-pct\');

            const hasDesc = descInput && descInput.value.trim() !== \'\';
            const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
            const discAmt = parseFloat(discAmtInput ? discAmtInput.value : 0) || 0;
            const discPct = parseFloat(discPctInput ? discPctInput.value : 0) || 0;

            return !hasDesc && price === 0 && discAmt === 0 && discPct === 0;
        }';

$newIsRowEmpty = 'if (type === \'service\') {
            const serviceSelect = row.querySelector(\'.service-picker\');
            const descInput = row.querySelector(\'.line-desc\');
            const priceInput = row.querySelector(\'.line-price\');
            const discAmtInput = row.querySelector(\'.line-disc-amt\');
            const discPctInput = row.querySelector(\'.line-disc-pct\');

            const hasService = serviceSelect && serviceSelect.value !== \'\';
            const hasDesc = descInput && descInput.value.trim() !== \'\';
            const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
            const discAmt = parseFloat(discAmtInput ? discAmtInput.value : 0) || 0;
            const discPct = parseFloat(discPctInput ? discPctInput.value : 0) || 0;

            return !hasService && !hasDesc && price === 0 && discAmt === 0 && discPct === 0;
        }';

// Normalize CRLF in strings before replace
$content = str_replace(
    str_replace("\r\n", "\n", $oldIsRowEmpty),
    str_replace("\r\n", "\n", $newIsRowEmpty),
    str_replace("\r\n", "\n", $content)
);

// 6. Update focusRowInput
$oldFocus = 'if (type === \'item\') {
            const select = row.querySelector(\'.product-picker\');
            if (select) select.focus();
        } else if (type === \'service\' || type === \'text\') {
            const input = row.querySelector(\'.line-desc\');
            if (input) input.focus();
        }';

$newFocus = 'if (type === \'item\') {
            const select = row.querySelector(\'.product-picker\');
            if (select) select.focus();
        } else if (type === \'service\') {
            const select = row.querySelector(\'.service-picker\');
            if (select) select.focus();
        } else if (type === \'text\') {
            const input = row.querySelector(\'.line-desc\');
            if (input) input.focus();
        }';

$content = str_replace(
    str_replace("\r\n", "\n", $oldFocus),
    str_replace("\r\n", "\n", $newFocus),
    $content
);

file_put_contents($filePath, $content);
echo "Successfully updated $filePath\n";

