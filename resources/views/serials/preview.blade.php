@extends(request()->boolean('embedded') ? 'serials.embedded' : 'layouts.app')
@section('title','Serial Number Preview')
@section('content')
@include('serials.common')
<section class="serial-card no-print"><h1 class="text-xl font-bold mb-4">{{ $saved ? 'Serial numbers saved' : 'Serial number preview' }}</h1>
<p class="mb-4">{{ count($rows) }} unique serials · {{ $data['copies'] }} copies each. {{ $saved ? 'Records are now Available in the system.' : 'Check the labels, then save. No records have been added yet.' }}</p>
<div class="flex flex-wrap gap-3">
@if(!$saved)<form method="POST" action="{{ route('products.serials.store') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">@if(request()->boolean('embedded'))<input type="hidden" name="embedded" value="1">@endif<button class="serial-btn">Save Serial Numbers</button></form>@else<button type="button" id="print-labels" class="serial-btn" onclick="window.print()">Print Labels</button>@endif
<a href="{{ route('products.serials.create', request()->boolean('embedded') ? ['embedded'=>1] : []) }}" class="serial-btn serial-secondary">New generation</a><a href="{{ route('products.serials.index') }}" @if(request()->boolean('embedded')) data-close-generator @endif class="serial-btn serial-secondary">{{ request()->boolean('embedded') ? 'Close' : 'Serial numbers' }}</a></div>
<p id="barcode-error" role="alert" class="text-rose-600 mt-4 hidden"></p>
<p class="text-sm text-slate-500 mt-4">Print at 100% scale with browser headers and footers turned off. Match the printer paper width to {{ $data['paper_width'] }} mm.</p></section>
<div class="overflow-auto"><div id="label-roll">
@foreach($rows as $row)@for($copy=0;$copy<$data['copies'];$copy++)<div class="label-row"><div class="serial-label"><svg class="serial-barcode" data-serial="{{ $row['serial_number'] }}"></svg></div></div>@endfor@endforeach
</div></div>
<style>
#label-roll{width:{{ $data['paper_width'] }}mm;background:white}
.label-row{width:100%;height:{{ $data['label_height'] + $data['gap'] + $data['y_offset'] }}mm;padding-top:{{ $data['y_offset'] }}mm;display:flex;justify-content:{{ ['left'=>'flex-start','center'=>'center','right'=>'flex-end'][$data['position']] }};padding-left:{{ $data['position']==='right'?0:$data['x_offset'] }}mm;padding-right:{{ $data['position']==='right'?$data['x_offset']:0 }}mm;max-width:100%;box-sizing:border-box}
.serial-label{width:{{ $data['label_width'] }}mm;height:{{ $data['label_height'] }}mm;flex-shrink:0;display:flex;align-items:center;justify-content:center;outline:1px dashed #ddd;padding:{{ $data['barcode_margin'] }}mm}
.serial-barcode{width:100%;height:{{ $data['barcode_height'] + ($data['show_text']?4:0) }}mm}
@media print{
body{margin:0!important;padding:0!important;height:auto!important}
body > *{display:none!important}
body > #label-roll{display:block!important}
body *{visibility:hidden}
#label-roll,#label-roll *{visibility:visible}
#label-roll{position:absolute;left:0;top:0}
.serial-label{outline:none}
.label-row{break-inside:avoid}
@page{size:{{ $data['paper_width'] }}mm {{ $data['label_height'] + $data['gap'] + $data['y_offset'] }}mm;margin:0}
}
</style>
@endsection
@push('scripts')
<script src="{{ asset('js/vendor/JsBarcode.all.min.js') }}"></script>
<script>
const roll=document.getElementById('label-roll');
const originalParent=roll.parentNode;
window.addEventListener('beforeprint',()=>document.body.appendChild(roll));
window.addEventListener('afterprint',()=>originalParent.appendChild(roll));
try {
document.querySelectorAll('.serial-barcode').forEach(svg=>{
JsBarcode(svg,svg.dataset.serial,{format:@json($data['barcode_format']),height:{{ $data['barcode_height'] * 3.78 }},displayValue:{{ $data['show_text']?'true':'false' }},fontSize:10,margin:0,marginLeft:10,marginRight:10,width:1});
svg.setAttribute('preserveAspectRatio','none');
svg.setAttribute('role','img');svg.setAttribute('aria-label',svg.dataset.serial);
});
} catch(error) { const msg=document.getElementById('barcode-error');msg.textContent='Barcodes could not be rendered. Please reload before printing.';msg.classList.remove('hidden');document.getElementById('print-labels')?.setAttribute('disabled','disabled'); }
</script>
@endpush
