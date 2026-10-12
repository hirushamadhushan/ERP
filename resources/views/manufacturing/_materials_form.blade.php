<section class="serial-card border-2 border-purple-200">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div><p class="text-xs font-bold uppercase tracking-widest text-purple-600">Manufacturing materials</p><h2>{{ $recipe->exists ? 'Edit product materials' : 'Set up product materials' }}</h2><p class="text-sm text-slate-500">Choose what is produced and the raw materials needed for the base quantity.</p></div>
        <a href="{{ route('manufacturing.create') }}" class="serial-btn serial-secondary">Back to order</a>
    </div>
    <form method="POST" action="{{ $recipe->exists ? route('manufacturing.boms.update', $recipe) : route('manufacturing.boms.store') }}">
        @csrf @if($recipe->exists) @method('PUT') @endif
        <div class="serial-grid">
            <div><label>Setup code *</label><input name="code" required maxlength="40" value="{{ old('code', $recipe->code) }}" placeholder="PROD-PACK-RICE-01"></div>
            <div><label>Finished product *</label><select name="product_id" required><option value="">Select product</option>@foreach($outputs as $product)<option value="{{ $product->id }}" @selected(old('product_id', $recipe->product_id) == $product->id)>{{ $product->name }} ({{ $product->code }})</option>@endforeach</select></div>
            <div><label>Base output quantity *</label><input type="number" min="0.0001" step="0.0001" name="output_quantity" required value="{{ old('output_quantity', $recipe->output_quantity ?: 1) }}"></div>
            <div class="flex items-center pt-6"><input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $recipe->exists ? $recipe->is_active : true))> Available for production</label></div>
            <div class="wide"><label>Notes</label><textarea name="notes" maxlength="2000">{{ old('notes', $recipe->notes) }}</textarea></div>
        </div>
        <div class="mt-6 mb-4 flex items-center justify-between gap-3"><div><h3 class="font-bold">Required raw materials</h3><p class="text-sm text-slate-500">Quantities scale automatically with production quantity.</p></div><button type="button" id="add-material" class="serial-btn serial-secondary">+ Add material</button></div>
        <div id="material-items" class="space-y-3"></div>
        <div class="mt-5 text-right"><button class="serial-btn">Save materials setup</button></div>
    </form>
</section>
<template id="material-item-template"><div class="material-row grid gap-3 rounded-xl border p-3 md:grid-cols-[1fr_1fr_180px_auto]"><div><label>Raw material *</label><select class="material-product" required><option value="">Select material</option>@foreach($materials as $material)<option value="{{ $material->id }}">{{ $material->name }} ({{ $material->code }}) · {{ $material->unit?->short_name }}</option>@endforeach</select></div><div class="variant-wrap" hidden><label>Variation *</label><select class="material-variant"><option value="">Select variation</option></select></div><div><label>Quantity *</label><input class="material-qty" type="number" min="0.0001" step="0.0001" required></div><button type="button" class="remove-material self-end py-2 text-rose-600">Remove</button></div></template>
@php
    $materialOptions = $materials->mapWithKeys(fn ($material) => [$material->id => ['type' => $material->product_type, 'variants' => $material->variants->map(fn ($variant) => ['id' => $variant->id, 'label' => ($variant->value ?: 'Variant').' ('.$variant->sku.')'])->values()]]);
    $existingMaterials = old('items', $recipe->items->map(fn ($item) => ['product_id' => $item->product_id, 'product_variant_id' => $item->product_variant_id, 'quantity' => $item->quantity])->values());
@endphp
@push('scripts')
<script>AppPage.ready(()=>{let index=0;const box=document.getElementById('material-items'),materials=@json($materialOptions),existing=@json($existingMaterials);function add(value={}){const row=document.getElementById('material-item-template').content.firstElementChild.cloneNode(true),current=index++,product=row.querySelector('.material-product'),variant=row.querySelector('.material-variant'),wrap=row.querySelector('.variant-wrap'),quantity=row.querySelector('.material-qty');product.name=`items[${current}][product_id]`;variant.name=`items[${current}][product_variant_id]`;quantity.name=`items[${current}][quantity]`;function sync(selected=''){const material=materials[product.value];variant.innerHTML='<option value="">Select variation</option>';(material?.variants||[]).forEach(option=>variant.add(new Option(option.label,option.id)));const variable=material?.type==='variable';wrap.hidden=!variable;variant.required=variable;variant.disabled=!variable;if(selected)variant.value=selected}product.value=value.product_id||'';sync(value.product_variant_id||'');product.onchange=()=>sync();quantity.value=value.quantity||'';row.querySelector('.remove-material').onclick=()=>row.remove();box.append(row)}document.getElementById('add-material').onclick=()=>add();existing.length?existing.forEach(add):add();document.getElementById('materials-setup').scrollIntoView({behavior:'smooth',block:'start'})});</script>
@endpush
