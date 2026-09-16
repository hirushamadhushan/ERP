@extends(request()->boolean('embedded') ? 'serials.embedded' : 'layouts.app')
@section('title','Serial Number Generator')
@section('content')
@include('serials.common')
@unless(request()->boolean('embedded'))
<div class="flex justify-between flex-wrap gap-3 mb-6"><h1 class="text-xl font-bold">Serial Numbers Generate & Print</h1><a href="{{ route('products.serials.index') }}" class="serial-btn serial-secondary">Back to serial numbers</a></div>
@endunless
@if($products->isEmpty() || $locations->isEmpty())<p class="p-4 mb-4 bg-amber-50 rounded-xl">Add a product with serial tracking enabled and assign business locations before generating.</p>@endif
<form action="{{ route('products.serials.preview') }}" method="POST" class="serial-card">@csrf
@if(request()->boolean('embedded'))<input type="hidden" name="embedded" value="1">@endif
<h2>Product</h2><div class="serial-grid">
@foreach(['product'=>'products','location'=>'locations'] as $type=>$collection)<div><label for="{{ $type }}">{{ ucfirst($type) }} *</label><select required name="{{ $type }}_id" id="{{ $type }}"><option value="">Please Select</option>@foreach($$collection as $record)<option value="{{ $record->id }}" @if($type==='product') data-locations="{{ $record->locations->pluck('id')->implode(',') }}" @endif @selected(old($type.'_id',request($type.'_id'))==$record->id)>{{ $record->name }} ({{ $record->code }})</option>@endforeach</select></div>@endforeach
<div><label for="variation">Variation</label><select id="variation" disabled><option>Default</option></select></div>
</div><hr><h2>Serial generator</h2><div class="serial-grid">
@foreach(['prefix'=>['Prefix (optional)','ABC'],'separator'=>['Separator','-'],'middle_fix'=>['Middle Fix',''],'post_fix'=>['Post Fix','']] as $name=>[$label,$placeholder])
<div><label for="{{ $name }}">{{ $label }}</label><input id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $name==='separator'?'-':'') }}" placeholder="{{ $placeholder }}" maxlength="{{ $name==='separator'?3:20 }}"></div>@endforeach
@foreach(['start_number'=>['Start Number',1,0,999999999],'count'=>['Count',20,1,1000],'padding'=>['Padding',4,1,12]] as $name=>[$label,$value,$min,$max])<div><label for="{{ $name }}">{{ $label }}</label><input type="number" id="{{ $name }}" name="{{ $name }}" value="{{ old($name,$value) }}" min="{{ $min }}" max="{{ $max }}" required></div>@endforeach
</div><p class="text-sm text-slate-500 mt-4">Order: Prefix · Middle Fix · Padded Number · Post Fix, joined with your separator. Example: ABC-0001. Empty parts are omitted.</p>
<hr><h2>Label settings</h2><div class="serial-grid">
@foreach(['paper_width'=>['Paper width (mm)',76,20,300],'label_width'=>['Label width (mm)',25,10,300],'label_height'=>['Label height (mm)',12,8,200],'x_offset'=>['X Offset (mm)',0,0,100],'y_offset'=>['Y Offset (mm)',0,0,100],'gap'=>['Gap (mm)',2,0,30],'copies'=>['Copies',1,1,10]] as $name=>[$label,$value,$min,$max])<div><label for="{{ $name }}">{{ $label }}</label><input type="number" step="{{ $name==='copies'?'1':'0.1' }}" id="{{ $name }}" name="{{ $name }}" value="{{ old($name,$value) }}" min="{{ $min }}" max="{{ $max }}" required></div>@endforeach
<div><label for="position">Label position on roll</label><select id="position" name="position">@foreach(['left','center','right'] as $position)<option value="{{ $position }}" @selected(old('position','right')===$position)>{{ strtoupper($position) }}</option>@endforeach</select></div>
</div><hr><h2>Barcode settings</h2><div class="serial-grid">
<div><label for="barcode_format">Barcode format</label><select name="barcode_format" id="barcode_format">@foreach(['CODE128','CODE39'] as $format)<option @selected(old('barcode_format','CODE128')===$format)>{{ $format }}</option>@endforeach</select></div>
@foreach(['barcode_height'=>['Barcode height (mm)',6.5,3,100],'barcode_margin'=>['Barcode margin (mm)',0,0,10]] as $name=>[$label,$value,$min,$max])<div><label for="{{ $name }}">{{ $label }}</label><input type="number" step="0.1" id="{{ $name }}" name="{{ $name }}" value="{{ old($name,$value) }}" min="{{ $min }}" max="{{ $max }}" required></div>@endforeach
<div><label for="show_text">Show barcode text</label><select id="show_text" name="show_text"><option value="0" @selected(!old('show_text'))>No</option><option value="1" @selected(old('show_text'))>Yes</option></select></div>
</div><button class="serial-btn mt-6">Generate Preview</button>
</form>
@endsection
@push('scripts')
<script>
const serialProduct=document.getElementById('product'),serialLocation=document.getElementById('location');
function syncSerialLocations(){
    const ids=(serialProduct.selectedOptions[0]?.dataset.locations||'').split(',');
    Array.from(serialLocation.options).forEach(option=>{if(option.value){option.disabled=!ids.includes(option.value);option.hidden=option.disabled;}});
    if(serialLocation.selectedOptions[0]?.disabled)serialLocation.value='';
    const available=Array.from(serialLocation.options).filter(option=>option.value&&!option.disabled);
    if(!serialLocation.value&&available.length===1)serialLocation.value=available[0].value;
}
serialProduct.addEventListener('change',syncSerialLocations);syncSerialLocations();
</script>
@endpush
