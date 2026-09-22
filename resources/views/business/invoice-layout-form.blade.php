@extends('layouts.app')
@section('title',$layout->exists?'Edit invoice layout':'Add new invoice layout')
@section('content')
@php
$editing=$layout->exists;$input='mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-100';
$labels=[
'document'=>['invoice_heading'=>'Invoice','heading_not_paid'=>'Heading Suffix for not paid','heading_paid'=>'Heading Suffix for paid','proforma_heading'=>'Proforma invoice','quotation_heading'=>'Quotation','sales_order_heading'=>'Sales Order','invoice_no_label'=>'Invoice No.','quotation_no_label'=>'Quotation number','date_label'=>'Date','due_date_label'=>'Due Date','date_time_format'=>'Date/time format','sales_person_label'=>'Sales Person Label','commission_agent_label'=>'Commission Agent'],
'customer'=>['customer_label'=>'Customer','client_id_label'=>'Client ID Label','client_tax_label'=>'Client tax number label'],
'product'=>['product_label'=>'Product','quantity_label'=>'Quantity','unit_price_label'=>'Unit Price','subtotal_label'=>'Subtotal','category_hsn_label'=>'HSN','total_quantity_label'=>'Total Quantity','item_discount_label'=>'Discount','discounted_price_label'=>'Price after discount','our_price_label'=>'Our Price'],
'totals'=>['subtotal_total_label'=>'Subtotal','discount_total_label'=>'Discount','tax_label'=>'Tax','total_label'=>'Total','total_items_label'=>'Total items label','round_off_label'=>'Round Off','total_due_label'=>'Due','amount_paid_label'=>'Total paid','all_sales_due_label'=>'Total Due Label','change_return_label'=>'Change Return','given_cash_label'=>'Given Cash','balance_cash_label'=>'Balance Cash','tax_summary_label'=>'Tax summary label'],
'credit'=>['credit_heading'=>'Credit Note','reference_number_label'=>'Reference No','credit_total_label'=>'Credit Amount']];
$options=['show_logo'=>'Show invoice Logo','show_business_name'=>'Show business name','show_location_name'=>'Show location name','show_sales_person'=>'Show Sales Person','show_commission_agent'=>'Show commission agent','show_customer_info'=>'Show Customer information','show_client_id'=>'Show client ID','show_reward_point'=>'Show reward point','location_landmark'=>'Landmark','location_city'=>'City','location_state'=>'State','location_country'=>'Country','location_zip'=>'Zip Code','communication_mobile'=>'Mobile number','communication_alternate'=>'Alternate number','communication_email'=>'Email','tax_1'=>'Tax 1 details','tax_2'=>'Tax 2 details','show_brand'=>'Show brand','show_sku'=>'Show SKU','show_category_hsn'=>'Show category code or HSN Code','show_sale_description'=>'Show sale description','show_product_image'=>'Show product image','show_warranty_name'=>'Show warranty name','show_warranty_expiry'=>'Show warranty expiry date','show_warranty_description'=>'Show warranty description','show_base_unit'=>'Show base unit details','show_payment_info'=>'Show Payment information','show_barcode'=>'Show Barcode','show_total_balance'=>'Show total balance due','show_total_in_words'=>'Show total in words','show_qr'=>'Show QR Code','show_qr_labels'=>'Show Labels','zatca_qr'=>'ZATCA QR code','qr_business_name'=>'Business Name','qr_location_address'=>'Business location address','qr_tax_1'=>'Business tax 1','qr_tax_2'=>'Business tax 2','qr_invoice_no'=>'Invoice No.','qr_datetime'=>'Invoice Datetime','qr_subtotal'=>'Subtotal','qr_total_taxed'=>'Total amount with tax','qr_total_tax'=>'Total Tax','qr_customer_name'=>'Customer name','qr_invoice_url'=>'Invoice URL'];
@endphp
<div class="mb-5 flex items-center justify-between"><div><h1 class="text-xl font-bold text-slate-900">{{ $editing?'Edit invoice layout':'Add new invoice layout' }}</h1><p class="mt-1 text-sm text-slate-500">Design the printed invoice and choose the information it displays.</p></div><a href="{{ route('business.invoice-settings.index',['tab'=>'layouts']) }}" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600">Back</a></div>
<form id="invoice-layout-form" method="POST" enctype="multipart/form-data" action="{{ $editing?route('business.invoice-settings.layouts.update',$layout):route('business.invoice-settings.layouts.store') }}" class="space-y-5">@csrf @if($editing)@method('PUT')@endif
@if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<section class="rounded-2xl border border-purple-100 bg-white p-5 shadow-sm"><div class="grid gap-5 md:grid-cols-2"><label class="text-xs font-bold">Layout name:*<input name="name" required value="{{ old('name',$layout->name) }}" class="{{ $input }}"></label><label class="text-xs font-bold">Design:*<select name="design" class="{{ $input }}">@foreach(['classic'=>'Classic (For normal printer)','elegant'=>'Elegant (For normal printer)','detailed'=>'Detailed (For normal printer)','columnize'=>'Columnize Taxes (For normal printer)','slim'=>'Slim (Recommended for thermal line receipt printer, 80mm paper size)','slim2'=>'Slim 2 (Recommended for thermal receipt printer)'] as $v=>$n)<option value="{{ $v }}" @selected(old('design',$layout->design?:'classic')===$v)>{{ $n }}</option>@endforeach</select><span class="mt-1 block font-normal text-slate-400">Used for browser based printing</span></label><label class="text-xs font-bold">Invoice Logo:<input type="file" name="logo" accept=".jpg,.jpeg,.png" class="{{ $input }}"><span class="mt-1 block font-normal text-slate-400">Max 1 MB; jpg, jpeg, png formats only.</span></label><label class="mt-6 flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="options[show_logo]" value="0"><input type="checkbox" name="options[show_logo]" value="1" @checked(old('options.show_logo',$layout->option('show_logo'))) class="accent-purple-600"> Show invoice Logo</label></div><label class="mt-5 block text-xs font-bold">Header text:<textarea name="header_text" id="header_text" rows="7" class="{{ $input }}" placeholder="Header text shown above invoice details">{{ old('header_text',$layout->header_text) }}</textarea></label><div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@for($i=1;$i<=5;$i++)<label class="text-xs font-bold">Sub Heading Line {{ $i }}:<input name="labels[sub_heading_{{ $i }}]" value="{{ old('labels.sub_heading_'.$i,$layout->label('sub_heading_'.$i)) }}" class="{{ $input }}"></label>@endfor</div></section>

@foreach(['document'=>'Invoice headings and labels','customer'=>'Fields for customer details','product'=>'Product details and labels','totals'=>'Totals, payments and balance labels'] as $group=>$title)
<section class="rounded-2xl border border-purple-100 bg-white p-5 shadow-sm"><h2 class="mb-4 font-bold text-purple-800">{{ $title }}</h2><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach($labels[$group] as $key=>$default)<label class="text-xs font-bold">{{ ucwords(str_replace('_',' ',$key)) }}:<input name="labels[{{ $key }}]" value="{{ old('labels.'.$key,$layout->label($key,$default)) }}" class="{{ $input }}"></label>@endforeach</div>
@php($groupOptions=$group==='customer'?array_slice($options,1,16,true):($group==='product'?array_slice($options,17,10,true):($group==='totals'?array_slice($options,27,4,true):[])))
@if($groupOptions)<div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach($groupOptions as $key=>$caption)<label class="flex items-center gap-2 text-xs text-slate-700"><input type="hidden" name="options[{{ $key }}]" value="0"><input type="checkbox" name="options[{{ $key }}]" value="1" @checked(old('options.'.$key,$layout->option($key))) class="accent-purple-600">{{ $caption }}</label>@endforeach</div>@endif</section>
@endforeach

<section class="rounded-2xl border border-purple-100 bg-white p-5 shadow-sm"><h2 class="mb-4 font-bold text-purple-800">Footer</h2><textarea name="footer_text" id="footer_text" rows="6" class="{{ $input }}" placeholder="Footer text shown at the bottom of the invoice">{{ old('footer_text',$layout->footer_text) }}</textarea></section>
<section class="rounded-2xl border border-purple-100 bg-white p-5 shadow-sm"><h2 class="mb-4 font-bold text-purple-800">QR Code</h2><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach(array_slice($options,31,null,true) as $key=>$caption)<label class="flex items-center gap-2 text-xs text-slate-700"><input type="hidden" name="options[{{ $key }}]" value="0"><input type="checkbox" name="options[{{ $key }}]" value="1" @checked(old('options.'.$key,$layout->option($key))) class="accent-purple-600">{{ $caption }}</label>@endforeach</div></section>
<details class="rounded-2xl border border-purple-100 bg-white p-5 shadow-sm"><summary class="cursor-pointer font-bold text-purple-800">Restaurant module settings</summary><p class="mt-3 text-sm text-slate-500">Restaurant-specific invoice fields will appear here when the restaurant module is enabled.</p></details>
<section class="rounded-2xl border border-purple-100 bg-white p-5 shadow-sm"><h2 class="mb-4 font-bold text-purple-800">Credit Note / Sell Return Details</h2><div class="grid gap-4 sm:grid-cols-3">@foreach($labels['credit'] as $key=>$default)<label class="text-xs font-bold">{{ ucwords(str_replace('_',' ',$key)) }}:<input name="labels[{{ $key }}]" value="{{ old('labels.'.$key,$layout->label($key,$default)) }}" class="{{ $input }}"></label>@endforeach</div></section>
<div class="flex items-center justify-between rounded-2xl border border-purple-100 bg-white px-5 py-4 shadow-sm"><label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="is_default" value="0"><input type="checkbox" name="is_default" value="1" @checked(old('is_default',$layout->is_default)) class="accent-purple-600"> Set as default</label><button class="rounded-xl bg-purple-600 px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-purple-500/20 hover:bg-purple-700">{{ $editing?'Update':'Save' }}</button></div>
</form>

<script src="{{ asset('js/vendor/tinymce/tinymce.min.js') }}"></script>
<script>
if (window.tinymce) {
    tinymce.init({
        selector: '#header_text, #footer_text',
        license_key: 'gpl',
        height: 280,
        menubar: 'file edit view insert format tools table help',
        plugins: 'lists table code help wordcount searchreplace visualblocks fullscreen',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | table | removeformat',
        toolbar_mode: 'sliding',
        promotion: false,
        branding: true,
        resize: true,
        setup: editor => {
            editor.on('change input undo redo', () => editor.save());
        }
    });

    document.getElementById('invoice-layout-form')?.addEventListener('submit', () => {
        window.tinymce?.triggerSave();
    });
}
</script>
@endsection
