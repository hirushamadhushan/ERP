@extends('layouts.app')
@section('title', $product->exists ? 'Edit Product' : 'Add Product')
@section('content')
@include('serials.common')
@php
$value=fn($key,$default='')=>old($key,$product->$key ?? $default);
$selectedLocations=old('location_ids',$product->exists?$product->locations->pluck('id')->all():[]);
$sellingInput=$product->selling_price ?? 0;
if($product->selling_price_tax_type==='inclusive') $sellingInput=round($sellingInput*(1+$product->tax_rate/100),4);
$savedVariants=old('variants',$product->exists?$product->variants->map(function($variant){return ['value'=>$variant->value,'sku'=>$variant->sku,'purchase_price'=>$variant->purchase_price,'selling_price'=>$variant->selling_price];})->values():[]);
$savedComboItems=old('combo_items',$product->exists?$product->comboItems->map(function($item){return ['product_id'=>$item->id,'quantity'=>$item->pivot->quantity];})->values():[]);
$comboProductOptions=$comboProducts->map(function($item){return ['id'=>$item->id,'name'=>$item->name,'code'=>$item->code,'purchase_price'=>$item->purchase_price,'selling_price'=>$item->selling_price];})->values();
@endphp
<style>
.product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
.product-grid .wide{grid-column:span 2}
.stock-field,.weight-field,.type-field{grid-column:1}
#product-form>.serial-card{padding:26px 24px 30px;margin-bottom:30px;border:1px solid #e8e4f2;border-top:2px solid #a78bfa;box-shadow:0 4px 18px rgb(30 41 59 / 3%);border-radius:16px}
#product-form>.serial-card:nth-of-type(2){border-top-color:#2dd4bf}
#product-form>.serial-card:nth-of-type(3){border-top-color:#a78bfa}
#product-form .product-grid{column-gap:30px;row-gap:20px}
#product-form label{font-size:13px;font-weight:650;color:#263248;margin-bottom:7px}
#product-form input:not([type=checkbox]):not([type=file]),#product-form select{font-size:13px;min-height:40px;padding:9px 12px;border-color:#dce0ee;border-radius:10px;transition:border-color .15s,box-shadow .15s}
#product-form input:focus,#product-form select:focus{outline:none;border-color:#a78bfa;box-shadow:0 0 0 3px #ede9fe}
#product-form input[type=file]{font-size:12px;padding:0;border:1px solid #dce0ee;border-radius:10px;min-height:40px;color:#64748b;overflow:hidden}
#product-form input[type=file]::file-selector-button{border:0;border-right:1px solid #ddd6fe;padding:11px 14px;margin-right:10px;background:#f3edff;color:#7c3aed;font-weight:600;cursor:pointer}
#product-form .product-pricing{table-layout:fixed;border:1px solid #e6e3ef}
#product-form .product-pricing th{background:#7761ac;color:white;font-size:12px;padding:10px;border-right:1px solid #ffffff30;white-space:normal}
#product-form .product-pricing th:first-child{width:38%}
#product-form .product-pricing th:last-child{width:25%}
#product-form .product-pricing td{padding:12px 10px;vertical-align:top;border-right:1px solid #eeeaf5}
#product-form .product-pricing .product-info{color:#d5fff5}
.product-actions{padding-top:6px;gap:0!important}
.product-actions .serial-btn{border-radius:0;padding:12px 20px;box-shadow:0 3px 8px #7c3aed18}
.product-actions .serial-btn:first-child{border-radius:24px 0 0 24px;background:#7561a8}
.product-actions .serial-btn:last-child{border-radius:0 24px 24px 0;background:#0d9488}
.product-help{color:#8490a4;font-size:11px;line-height:1.6;margin-top:6px}
.quick-add{border:1px solid #ddd6fe;border-radius:10px;color:#9333ea;font-size:22px;padding:0 12px;height:44px;align-self:flex-start}
.serial-card input[type=checkbox]{accent-color:#9333ea;width:16px;height:16px;margin-right:6px;vertical-align:middle}
.product-info{cursor:help;color:#9333ea}
#product-description{min-height:190px;padding:14px;outline:none;overflow-wrap:anywhere;font-size:14px}
#product-description ul{list-style:disc;padding-left:20px}#product-description ol{list-style:decimal;padding-left:20px}
#product-description p{margin-bottom:8px}
#reference-dialog{position:fixed;inset:0;margin:auto;border:0;padding:24px;border-radius:16px;width:min(440px,calc(100vw - 24px));max-height:calc(100dvh - 32px);overflow:auto}
#reference-dialog::backdrop{background:#0f172a88;backdrop-filter:blur(3px)}
.type-panel{margin-top:22px;border:1px solid #e6e3ef;border-radius:14px;overflow:hidden;background:#fff}.type-panel-title{padding:12px 16px;background:#f4f0ff;color:#5b3f95;font-weight:700}.dynamic-table th{background:#7761ac!important}.row-remove{width:30px;height:30px;border-radius:50%;background:#f43f5e;color:white}.row-add{width:34px;height:34px;border-radius:50%;background:#14b8a6;color:white;font-size:20px}
@media(max-width:800px){.product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:500px){.product-grid{grid-template-columns:minmax(0,1fr)}.product-grid .wide{grid-column:span 1}}
@media(max-width:500px){#product-form>.serial-card{padding:20px 16px}.product-actions{gap:8px!important}.product-actions .serial-btn{border-radius:12px!important;width:100%}}
</style>
<div class="flex flex-wrap justify-between gap-3 mb-6"><h1 class="text-xl font-bold">{{ $product->exists?'Edit product':'Add new product' }}</h1><a class="serial-btn serial-secondary" href="{{ route('products.catalog.index') }}">Products</a></div>
<form id="product-form" method="POST" enctype="multipart/form-data" action="{{ $product->exists?route('products.catalog.update',$product):route('products.catalog.store') }}">@csrf
@if($product->exists) @method('PUT') @endif
<section class="serial-card"><div class="product-grid">
<div><label for="name">Product Name *</label><input id="name" name="name" required maxlength="255" placeholder="Product Name" value="{{ $value('name') }}"></div>
<div><label for="sku">SKU <span class="product-info" tabindex="0" title="Enter a unique product code using letters, numbers, dots, hyphens or underscores. Leave empty to generate one automatically.">â“˜</span></label><input id="sku" name="sku" maxlength="100" placeholder="Leave blank to auto-generate" value="{{ old('sku',$product->code) }}"><p class="product-help">Your own code, e.g. PUMP-001, or an automatic PRD-â€¦ code.</p></div>
<div><label for="barcode_type">Barcode Type *</label><select name="barcode_type" id="barcode_type">@foreach(['CODE128'=>'Code 128 (C128)','CODE39'=>'Code 39 (C39)'] as $key=>$label)<option value="{{ $key }}" @selected($value('barcode_type','CODE128')===$key)>{{ $label }}</option>@endforeach</select></div>
<div><label for="unit_id">Unit *</label><div class="flex gap-2"><select required id="unit_id" name="unit_id"><option value="">Please Select</option>@foreach($units as $unit)<option value="{{ $unit->id }}" data-decimal="{{ (int)$unit->allow_decimal }}" @selected($value('unit_id')==$unit->id)>{{ $unit->name }} ({{ $unit->short_name }})</option>@endforeach</select><button type="button" class="quick-add" data-reference="unit" aria-label="Add unit">+</button></div></div>
<div><label for="brand_id">Brand</label><div class="flex gap-2"><select id="brand_id" name="brand_id"><option value="">Please Select</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected($value('brand_id')==$brand->id)>{{ $brand->name }}</option>@endforeach</select><button type="button" class="quick-add" data-reference="brand" aria-label="Add brand">+</button></div></div>
<div><label for="category_id">Category</label><div class="flex gap-2"><select id="category_id" name="category_id"><option value="">Please Select</option>@foreach($categories->whereNull('parent_id') as $category)<option value="{{ $category->id }}" @selected($value('category_id')==$category->id)>{{ $category->name }}</option>@endforeach</select></div></div>
<div><label for="subcategory_id">Sub category</label><div class="flex gap-2"><select id="subcategory_id" name="subcategory_id"><option value="">Please Select</option>@foreach($categories->whereNotNull('parent_id') as $category)<option data-parent="{{ $category->parent_id }}" value="{{ $category->id }}" @selected($value('subcategory_id')==$category->id)>{{ $category->name }}</option>@endforeach</select></div></div>
<div><label for="location_ids">Business Locations * <span class="product-info" tabindex="0" title="Choose the branches or warehouses that stock this product. Serial import and generation use only these locations.">â“˜</span></label><div class="flex gap-2"><select id="location_ids" name="location_ids[]" multiple required size="2">@foreach($locations as $location)<option value="{{ $location->id }}" @selected(in_array($location->id,$selectedLocations))>{{ $location->name }} ({{ $location->code }})</option>@endforeach</select></div><p class="product-help">Select all locations for this product. Hold Ctrl / Cmd to choose multiple locations.</p></div>
<div class="stock-field"><input type="hidden" name="manage_stock" value="0"><label class="flex items-center gap-2" for="manage_stock"><input type="checkbox" id="manage_stock" name="manage_stock" value="1" @checked($value('manage_stock',true))> Manage Stock?</label><p class="product-help">Enable stock management at product level.</p></div>
<div><label for="alert_quantity">Alert quantity <span class="product-info" tabindex="0" title="The minimum quantity to use for low-stock checks.">â“˜</span></label><input type="number" min="0" max="999999999" step="0.0001" name="alert_quantity" id="alert_quantity" value="{{ $value('alert_quantity') }}" placeholder="Alert quantity"></div>
<div></div>
<div class="wide"><label for="description">Product Description</label><textarea name="description" id="description" rows="10" class="w-full border border-slate-300 rounded-xl p-3">{{ \App\Services\ProductDescription::clean($value('description')) }}</textarea></div>
<div><label for="image">Product image</label><input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp"><p class="product-help">Max 5 MB. Square images work best.</p>@if($product->image_path)<img class="mt-3 rounded-xl max-h-36" alt="Current product image" src="{{ route('products.catalog.attachment',[$product,'image']) }}">@endif<img id="image-preview" class="hidden mt-3 rounded-xl max-h-36" alt="Selected product image"></div>
<div class="wide"><label for="brochure">Product brochure</label><input type="file" id="brochure" name="brochure" accept=".pdf,.csv,.txt,.zip,.doc,.docx,.jpeg,.jpg,.png"><p class="product-help">Max 5 MB. PDF, CSV, TXT, ZIP, DOC, DOCX, JPG or PNG.</p>@if($product->brochure_path)<a class="text-sm text-purple-700" href="{{ route('products.catalog.attachment',[$product,'brochure']) }}">Download current brochure</a>@endif</div>
</div></section>
<section class="serial-card"><div class="product-grid">
<div><input type="hidden" name="enable_serial" value="0"><label for="enable_serial"><input type="checkbox" id="enable_serial" name="enable_serial" value="1" @checked($value('enable_serial',false))> Enable IMEI / Serial Number tracking <span class="product-info" tabindex="0" title="Each physical item receives one unique serial. Enables the serial generator and Excel import for this product.">â“˜</span></label><p class="product-help">Requires stock management and a whole-number unit.</p></div>
<div><input type="hidden" name="not_for_selling" value="0"><label for="not_for_selling"><input type="checkbox" id="not_for_selling" name="not_for_selling" value="1" @checked($value('not_for_selling',false))> Not for selling <span class="product-info" tabindex="0" title="Mark items that should not be offered for sale.">â“˜</span></label></div>
<div class="weight-field"><label for="weight">Weight</label><input id="weight" name="weight" maxlength="100" value="{{ $value('weight') }}" placeholder="e.g. 2 kg"></div>
</div><div class="serial-grid mt-6">@for($i=0;$i<4;$i++)<div><label for="custom-{{ $i }}">Custom Field {{ $i+1 }}</label><input id="custom-{{ $i }}" name="custom_fields[{{ $i }}]" maxlength="255" value="{{ old('custom_fields.'.$i,$product->custom_fields[$i]??'') }}"></div>@endfor</div></section>
<section class="serial-card"><div class="product-grid">
<div><label for="tax_mode">Applicable Tax</label><select id="tax_mode"><option value="none" @selected((float)$value('tax_rate',0)===0)>None</option><option value="custom" @selected((float)$value('tax_rate',0)>0)>Custom rate (%)</option></select><input aria-label="Tax rate percentage" class="mt-2" type="number" name="tax_rate" id="tax_rate" min="0" max="100" step="0.001" required value="{{ $value('tax_rate',0) }}"></div>
<div><label for="selling_price_tax_type">Selling Price Tax Type *</label><select name="selling_price_tax_type" id="selling_price_tax_type"><option value="exclusive" @selected($value('selling_price_tax_type','exclusive')==='exclusive')>Exclusive</option><option value="inclusive" @selected($value('selling_price_tax_type')==='inclusive')>Inclusive</option></select></div>
<div class="type-field"><label for="product_type">Product Type *</label><select name="product_type" id="product_type">@foreach(['single'=>'Single','variable'=>'Variable','combo'=>'Combo'] as $key=>$label)<option value="{{ $key }}" @selected($value('product_type','single')===$key)>{{ $label }}</option>@endforeach</select><p class="product-help">Variable creates prices for variation values. Combo creates a bundle from existing products.</p></div>
</div><div id="single-pricing">
<div class="overflow-x-auto mt-6"><table class="serial-table product-pricing" style="min-width:730px"><thead><tr><th colspan="2">Default Purchase Price</th><th>Margin (%) <span class="product-info" tabindex="0" title="Markup on purchase price excluding tax. Editing the selling price recalculates the margin.">â“˜</span></th><th>Default Selling Price</th><th>Product image</th></tr></thead><tbody><tr>
<td><label for="purchase_price">Exc. tax *</label><input type="number" min="0" max="999999999" step="0.0001" required name="purchase_price" id="purchase_price" value="{{ $value('purchase_price',0) }}"></td>
<td><label for="purchase_price_inc">Inc. tax</label><input type="number" min="0" step="0.0001" id="purchase_price_inc" value="{{ $value('purchase_price_inc',0) }}"></td>
<td><label for="margin">Margin</label><input type="number" min="-100" max="999999" step="any" name="margin" id="margin" value="{{ $value('margin',25) }}"></td>
<td><label id="selling-label" for="selling_price">Exc. tax *</label><input type="number" min="0" max="999999999" step="0.0001" required name="selling_price" id="selling_price" value="{{ old('selling_price',$sellingInput) }}"></td>
<td><label for="variant_image">Product image</label><input type="file" name="variant_image" id="variant_image" accept=".jpg,.jpeg,.png,.webp"><p class="product-help">Max 5 MB; square image.</p>@if($product->variant_image_path)<a href="{{ route('products.catalog.attachment',[$product,'variant_image']) }}" class="text-purple-700">Current image</a>@endif</td>
</tr></tbody></table></div><div class="max-w-sm mt-6"><label for="our_price">Our Price</label><input type="number" min="0" max="999999999" step="0.0001" name="our_price" id="our_price" placeholder="Our Price" value="{{ $value('our_price') }}"></div></div>
<div id="variable-pricing" class="type-panel" hidden><div class="type-panel-title">Variation pricing</div><div class="p-4"><label for="variation_template_id">Add Variation *</label><select id="variation_template_id" name="variation_template_id"><option value="">Please Select</option>@foreach($variationTemplates as $template)<option value="{{ $template->id }}" data-values='@json($template->values)' @selected(old('variation_template_id',$product->variants->first()?->variation_template_id)==$template->id)>{{ $template->name }}</option>@endforeach</select><div class="overflow-x-auto mt-4"><table class="serial-table product-pricing dynamic-table" style="min-width:940px"><thead><tr><th>SKU</th><th>Value</th><th>Purchase Price</th><th>Margin (%)</th><th>Selling Price</th><th>Variation Image</th><th></th></tr></thead><tbody id="variant-rows"></tbody></table></div></div></div>
<div id="combo-pricing" class="type-panel" hidden><div class="type-panel-title">Combo products</div><div class="p-4"><p class="product-help mb-3">Add products and quantities included in this bundle.</p><div class="overflow-x-auto"><table class="serial-table product-pricing dynamic-table"><thead><tr><th>Product</th><th>Quantity</th><th></th></tr></thead><tbody id="combo-rows"></tbody></table></div><button type="button" id="add-combo-row" class="row-add mt-3" aria-label="Add combo item">+</button></div></div>
</section>
<div class="product-actions flex flex-wrap justify-center gap-3 mb-8"><button class="serial-btn" name="save_action" value="opening">Save & Add Opening Stock</button><button class="serial-btn" style="background:#db2777" name="save_action" value="another">Save And Add Another</button><button class="serial-btn" name="save_action" value="save">Save</button></div>
</form>
<dialog id="reference-dialog" aria-labelledby="reference-title"><form id="reference-form" class="serial-card" style="padding:0;border:0;margin:0">
<h2 id="reference-title">Add reference</h2><p id="reference-error" role="alert" class="text-rose-600 mb-3"></p>
<label for="reference-name">Name *</label><input id="reference-name" name="name" required maxlength="100">
<div data-ref-kind="unit" class="mt-4"><label for="reference-short">Short name *</label><input id="reference-short" name="short_name" maxlength="30"><label class="mt-4" for="reference-decimal">Allow decimal *</label><select id="reference-decimal" name="allow_decimal"><option value="0">No</option><option value="1">Yes</option></select></div>
<div data-ref-kind="location" class="mt-4"><label for="reference-code">Location code *</label><input id="reference-code" name="code" maxlength="100"></div>
<div class="flex justify-end gap-3 mt-6"><button type="button" class="serial-btn serial-secondary" id="reference-close">Close</button><button id="reference-save" class="serial-btn">Save</button></div>
</form></dialog>
<script type="application/json" id="saved-variants">@json($savedVariants)</script>
<script type="application/json" id="saved-combo-items">@json($savedComboItems)</script>
@endsection
@push('scripts')
@include('products.form-script')
@endpush
