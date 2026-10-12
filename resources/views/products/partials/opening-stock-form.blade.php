@php($modal = $modal ?? false)
<style>
.opening-modal-shell{background:rgb(8 20 40/.68);backdrop-filter:blur(4px)}.opening-modal-panel{border:1px solid rgb(255 255 255/.24);background:#edf7f8;box-shadow:0 30px 80px rgb(2 20 38/.38)}.opening-modal-header{background:linear-gradient(120deg,#173f67 0%,#0d6571 58%,#078b86 100%);color:#f7ffff;border-bottom:1px solid rgb(255 255 255/.18)}.opening-modal-icon{display:grid;width:38px;height:38px;flex:none;place-items:center;border:1px solid rgb(255 255 255/.25);border-radius:12px;background:rgb(255 255 255/.13);font-size:18px}.opening-modal-close{display:grid;width:34px;height:34px;place-items:center;border-radius:10px;color:#d7f5f3;transition:.18s ease}.opening-modal-close:hover{background:rgb(255 255 255/.16);color:#fff}.opening-modal-body{background:linear-gradient(180deg,#f1f9f9,#e8f2f5)}
.opening-stock-scroll{max-height:min(62vh,620px);overflow:auto;padding:22px!important;background:transparent}.opening-location{border:1px solid #c7dcdf!important;background:#fbfefe!important;box-shadow:0 9px 24px rgb(21 75 84/.09)!important}.opening-location>h3{border-bottom:1px solid #c7e4e3!important;background:linear-gradient(90deg,#dff4f2,#eaf7f7 65%,#f5fbfb)!important;color:#164e63!important;letter-spacing:.01em}.opening-location>h3:before{display:inline-block;width:7px;height:7px;margin-right:9px;border-radius:999px;background:#0d9488;box-shadow:0 0 0 4px rgb(13 148 136/.12);content:'';vertical-align:1px}.opening-stock-table{min-width:980px;border-collapse:separate;border-spacing:0}.opening-stock-table th{padding:12px 10px;background:linear-gradient(180deg,#167a83,#126772);color:#f0fdfa;font-size:11.5px;font-weight:800;letter-spacing:.015em;text-align:center}.opening-stock-table td{border-right:1px solid #dce8ea;border-bottom:1px solid #dce8ea;padding:8px;vertical-align:top;background:#fbfefe;color:#243b53}.opening-stock-table tbody tr:nth-child(even) td{background:#f4f9fa}.opening-stock-table tbody tr:hover td{background:#edf8f7}.opening-stock-table input,.opening-stock-table textarea{width:100%;border:1px solid #bfd0d5;border-radius:10px;padding:9px 10px;background:#fff;color:#183446;font-size:12px;box-shadow:0 1px 2px rgb(15 23 42/.03);transition:border-color .16s,box-shadow .16s}.opening-stock-table input:focus,.opening-stock-table textarea:focus{border-color:#0d9488;outline:0;background:#fff;box-shadow:0 0 0 3px rgb(20 184 166/.15)}.opening-stock-table tfoot td{border-color:#ead9a9;background:#fff8e5;color:#744b0b}.opening-stock-table tfoot .opening-location-total{font-size:14px;color:#9a5a05}.opening-stock-form>.border-t{border-color:#c7dcdf!important;background:#e1eef0}.opening-stock-form .serial-btn{border:0!important;background:linear-gradient(135deg,#0f8a83,#087578)!important;color:#fff!important;box-shadow:0 7px 16px rgb(13 148 136/.22)}.opening-stock-form .serial-btn:hover{background:linear-gradient(135deg,#0d7d76,#075f68)!important;transform:translateY(-1px)}.opening-stock-form .serial-secondary{border:1px solid #bfd0d5!important;background:#f7fbfc!important;color:#385466!important;box-shadow:none}.opening-stock-form .serial-secondary:hover{background:#e7f1f3!important;color:#173f67!important}
/* Refined blue/slate palette using clear surface, container and action roles. */
.opening-modal-shell{background:rgb(15 23 42/.68);backdrop-filter:blur(5px)}
.opening-modal-panel{border:1px solid #cbd5e1;background:#f8fafc;box-shadow:0 28px 70px rgb(15 23 42/.32)}
.opening-modal-header{border-bottom:1px solid #334155;background:linear-gradient(115deg,#111827,#1e293b 58%,#243b5a);color:#f8fafc}
.opening-modal-icon{border-color:rgb(147 197 253/.34);background:#2563eb;color:#fff;box-shadow:0 6px 16px rgb(37 99 235/.28)}
.opening-modal-close{color:#cbd5e1}.opening-modal-close:hover{background:#334155;color:#fff}
.opening-modal-body,.opening-stock-scroll{background:#f1f5f9}
.opening-location{border-color:#d8e1ec!important;background:#fff!important;box-shadow:0 8px 22px rgb(30 41 59/.07)!important}
.opening-location>h3{border-bottom:1px solid #dbeafe!important;border-left:4px solid #3b82f6!important;background:#eff6ff!important;color:#1e3a5f!important}
.opening-location>h3:before{background:#3b82f6;box-shadow:0 0 0 4px rgb(59 130 246/.12)}
.opening-stock-table th{background:#334155;color:#f8fafc;letter-spacing:.01em}
.opening-stock-table td{border-color:#e2e8f0;background:#fff;color:#334155}
.opening-stock-table tbody tr:nth-child(even) td{background:#f8fafc}.opening-stock-table tbody tr:hover td{background:#f5f9ff}
.opening-stock-table input,.opening-stock-table textarea{border-color:#cbd5e1;background:#fff;color:#1e293b;box-shadow:0 1px 2px rgb(15 23 42/.04)}
.opening-stock-table input:focus,.opening-stock-table textarea:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgb(59 130 246/.14)}
.opening-stock-table tfoot td{border-color:#bfdbfe;background:#eff6ff;color:#1e3a8a}.opening-stock-table tfoot .opening-location-total{color:#1d4ed8}
.opening-stock-form>.border-t{border-color:#d8e1ec!important;background:#f8fafc}
.opening-stock-form .serial-btn{background:#2563eb!important;color:#fff!important;box-shadow:0 7px 16px rgb(37 99 235/.22)}.opening-stock-form .serial-btn:hover{background:#1d4ed8!important}
.opening-stock-form .serial-secondary{border-color:#cbd5e1!important;background:#fff!important;color:#475569!important;box-shadow:0 1px 2px rgb(15 23 42/.04)}.opening-stock-form .serial-secondary:hover{border-color:#94a3b8!important;background:#f1f5f9!important;color:#1e293b!important}
</style>
@if(!$product->manage_stock)
 <div class="p-6"><p class="rounded-xl bg-amber-50 p-4 text-sm font-semibold text-amber-800">Enable Manage Stock on this product before adding opening stock.</p><div class="mt-4 flex justify-end gap-3"><a class="serial-btn" href="{{ route('products.catalog.edit', $product) }}">Edit Product</a>@if($modal)<button type="button" class="close-opening-stock-modal serial-btn serial-secondary">Close</button>@endif</div></div>
@else
 <form @if($modal)data-async-form @endif class="opening-stock-form" method="POST" action="{{ route('products.catalog.opening.store', $product) }}">
  @csrf
  @unless($modal)<input type="hidden" name="return_to_products" value="1">@endunless
  <div class="opening-stock-scroll p-4 sm:p-6">
   @foreach($product->locations as $location)
    <section class="opening-location mb-5 overflow-hidden rounded-2xl border border-purple-100 bg-white shadow-sm">
     <h3 class="border-b border-purple-100 bg-purple-50 px-5 py-4 font-extrabold text-slate-800">Location: {{ $location->name }} ({{ $location->code }})</h3>
     <div class="overflow-x-auto"><table class="opening-stock-table w-full text-sm"><thead><tr><th>Product Name</th><th>Quantity Remaining</th><th>Unit Cost (Before Tax)</th>@if($expiryEnabled)<th>Exp. Date</th>@endif<th>Subtotal (Before Tax)</th><th>Date</th><th>Note</th></tr></thead><tbody>
      @if($product->product_type === 'variable')
       @foreach($product->variants as $variant)
        @php($stock = $variant->locationStocks->firstWhere('location_id', $location->id))
        @include('products.partials.opening-stock-row', ['key' => $variant->id.'.'.$location->id, 'quantityName' => 'quantities['.$variant->id.']['.$location->id.']', 'fieldPrefix' => fn($name) => $name.'['.$variant->id.']['.$location->id.']', 'label' => $product->name.' — '.$variant->value, 'record' => $stock])
       @endforeach
      @else
       @include('products.partials.opening-stock-row', ['key' => $location->id, 'quantityName' => 'quantities['.$location->id.']', 'fieldPrefix' => fn($name) => $name.'['.$location->id.']', 'label' => $product->name, 'record' => $location->pivot])
      @endif
     </tbody><tfoot><tr><td></td><td class="px-4 py-3 text-right font-bold text-slate-700">Total Amount (Exc. Tax):</td><td class="opening-location-total px-4 py-3 text-center font-bold" colspan="{{ $expiryEnabled ? 5 : 4 }}">Rs 0.00</td></tr></tfoot></table></div>
    </section>
   @endforeach
   @if($product->locations->isEmpty())<p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Assign at least one business location to this product first.</p>@endif
  </div>
  <div class="flex justify-end gap-3 border-t border-purple-100 px-5 py-4"><button type="submit" class="serial-btn" @disabled($product->locations->isEmpty())>Save</button>@if($modal)<button type="button" class="close-opening-stock-modal serial-btn serial-secondary">Close</button>@else<a class="serial-btn serial-secondary" href="{{ route('products.catalog.index') }}">Close</a>@endif</div>
 </form>
@endif
