@extends('layouts.app')
@section('title', 'Product Lots')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-xl font-bold">Lots — {{ $product->name }}</h1><p class="mt-1 text-sm text-slate-500">SKU: {{ $product->code }}. Receipts with matching batch, dates and prices are grouped into the same lot.</p></div>
    <a class="serial-btn serial-secondary" href="{{ route('products.catalog.index') }}">Products</a>
</div>

@if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-800">{{ session('error') }}</div>@endif

<section class="serial-card mb-6">
    <h2 class="mb-4 text-lg font-bold">Receive stock into a lot</h2>
    <form method="POST" action="{{ route('products.catalog.lots.store', $product) }}" class="grid gap-4 md:grid-cols-3">
        @csrf
        <div><label for="location_id">Business Location *</label><select id="location_id" name="location_id" required><option value="">Select location</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id', $locations->count() === 1 ? $locations->first()->id : '')==$location->id)>{{ $location->name }} ({{ $location->code }})</option>@endforeach</select>@error('location_id')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror</div>
        @if($product->product_type === 'variable')<div><label for="variant_id">Variation *</label><select id="variant_id" name="variant_id" required><option value="">Select variation</option>@foreach($product->variants as $variant)<option value="{{ $variant->id }}" @selected(old('variant_id')==$variant->id)>{{ $variant->value }} — {{ $variant->sku }}</option>@endforeach</select>@error('variant_id')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror</div>@endif
        <div><label for="supplier_lot_code">Supplier Batch / Lot Code</label><input id="supplier_lot_code" name="supplier_lot_code" maxlength="100" value="{{ old('supplier_lot_code') }}" placeholder="Supplier batch number">@error('supplier_lot_code')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror</div>
        <div><label for="manufactured_at">Manufacturing Date</label><input id="manufactured_at" type="date" name="manufactured_at" value="{{ old('manufactured_at') }}">@error('manufactured_at')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror</div>
        <div><label for="expires_at">Expiry Date</label><input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at') }}">@error('expires_at')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror</div>
        <div><label for="quantity">Received Quantity *</label><input id="quantity" type="number" min="0.0001" step="{{ $product->unit?->allow_decimal ? '0.0001' : '1' }}" name="quantity" value="{{ old('quantity') }}" required>@error('quantity')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror</div>
        <div><label for="unit_cost">Unit Cost *</label><input id="unit_cost" type="number" min="0" step="0.0001" name="unit_cost" value="{{ old('unit_cost', $product->purchase_price) }}" required>@error('unit_cost')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror</div>
        <div><label for="selling_price">Lot Selling Price *</label><input id="selling_price" type="number" min="0" step="0.0001" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}" required>@error('selling_price')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror</div>
        <div><label for="reference">Reference</label><input id="reference" name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="Invoice / receipt no."></div>
        <div class="md:col-span-3"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="2000" class="w-full rounded-xl border border-slate-300 p-3">{{ old('notes') }}</textarea></div>
        <div class="md:col-span-3">@if($errors->has('product'))<p class="mb-3 text-sm text-rose-600">{{ $errors->first('product') }}</p>@endif<button class="serial-btn" type="submit">Receive stock</button></div>
    </form>
</section>

<section class="serial-card">
    <h2 class="mb-4 text-lg font-bold">Lot balances</h2>
    <div class="overflow-x-auto"><table class="serial-table min-w-full"><thead><tr><th>Lot</th><th>Supplier Batch</th><th>Variation</th><th>Manufactured</th><th>Expires</th><th>Unit Cost</th><th>Selling Price</th><th>On Hand by Location</th></tr></thead><tbody>
        @forelse($lots as $lot)<tr><td>{{ $lot->lot_number }}</td><td>{{ $lot->supplier_lot_code ?: '—' }}</td><td>{{ $lot->stockItem?->variant?->first()?->value ?: 'Default' }}</td><td>{{ $lot->manufactured_at?->format('Y-m-d') ?: '—' }}</td><td>{{ $lot->expires_at?->format('Y-m-d') ?: '—' }}</td><td>Rs {{ number_format((float)$lot->unit_cost, 2) }}</td><td>Rs {{ number_format((float)$lot->selling_price, 2) }}</td><td class="font-bold">@forelse($lotLocationBalances->get($lot->id, collect()) as $balance)<div>{{ $balance->location_name }}: {{ number_format((float)$balance->quantity, $product->unit?->allow_decimal ? 2 : 0) }} {{ $product->unit?->short_name }}</div>@empty 0 @endforelse</td></tr>
        @empty<tr><td colspan="8" class="py-6 text-center text-slate-500">No lots received for this product yet.</td></tr>@endforelse
    </tbody></table></div>
    <div class="mt-4">{{ $lots->links() }}</div>
</section>

<section class="serial-card mt-6">
    <h2 class="mb-4 text-lg font-bold">Recent stock movements</h2>
    <div class="overflow-x-auto"><table class="serial-table min-w-full"><thead><tr><th>Date</th><th>Type</th><th>Lot</th><th>Location</th><th>Quantity Change</th><th>Reference</th></tr></thead><tbody>
        @forelse($movements as $movement)<tr><td>{{ $movement->created_at?->format('Y-m-d H:i') }}</td><td>{{ ucfirst($movement->transaction?->transaction_type ?? 'movement') }}</td><td>{{ $movement->lot?->lot_number }}</td><td>{{ $movement->location?->name }}</td><td class="{{ $movement->quantity_delta < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ number_format((float)$movement->quantity_delta, 4) }}</td><td>{{ $movement->transaction?->reference ?: '—' }}</td></tr>
        @empty<tr><td colspan="6" class="py-6 text-center text-slate-500">No stock movements recorded yet.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
