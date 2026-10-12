@php
 $quantity = old(str_replace(['[', ']'], ['.', ''], $quantityName), $record?->opening_quantity ?? 0);
 $costName = $fieldPrefix('unit_costs');
 $expiryName = $fieldPrefix('expiry_dates');
 $dateName = $fieldPrefix('stocked_at');
 $noteName = $fieldPrefix('notes');
 $cost = old(str_replace(['[', ']'], ['.', ''], $costName), $record?->opening_unit_cost ?? $product->purchase_price);
 $expiry = old(str_replace(['[', ']'], ['.', ''], $expiryName), $record?->opening_expiry_date);
 $stockedAt = old(str_replace(['[', ']'], ['.', ''], $dateName), $record?->opening_stocked_at ? \Illuminate\Support\Carbon::parse($record->opening_stocked_at)->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
@endphp
<tr class="opening-stock-row">
 <td class="font-semibold text-slate-700">{{ $label }}</td>
 <td><div class="flex min-w-36 items-center"><input class="opening-quantity" type="number" min="0" max="999999999" step="{{ $product->unit?->allow_decimal ? '0.0001' : '1' }}" name="{{ $quantityName }}" value="{{ $quantity }}" required><span class="rounded-r-lg border border-l-0 border-slate-200 bg-slate-50 px-3 py-2.5 text-xs">{{ $product->unit?->short_name }}</span></div></td>
 <td><input class="opening-unit-cost" type="number" min="0" max="999999999" step="0.0001" name="{{ $costName }}" value="{{ $cost }}" required></td>
 @if($expiryEnabled)<td><input type="date" name="{{ $expiryName }}" value="{{ $expiry }}"></td>@endif
 <td class="opening-subtotal text-center font-semibold text-slate-700">{{ number_format((float)$quantity * (float)$cost, 2) }}</td>
 <td><input type="datetime-local" name="{{ $dateName }}" value="{{ $stockedAt }}"></td>
 <td><textarea name="{{ $noteName }}" rows="2" maxlength="1000">{{ old(str_replace(['[', ']'], ['.', ''], $noteName), $record?->opening_note) }}</textarea></td>
</tr>
