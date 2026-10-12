@extends('layouts.app')
@section('title','Opening Stock')
@section('content')
@include('serials.common')
<div id="opening-stock-toast" class="fixed right-5 top-3 z-[90] hidden w-[min(360px,calc(100%-2rem))] rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-2xl" role="status"><i class="bi bi-check-circle-fill mr-2"></i><span id="opening-stock-toast-message"></span></div>
<div class="flex flex-wrap justify-between gap-3 mb-6"><div><h1 class="text-xl font-bold">Opening Stock — {{ $product->name }}</h1><p class="text-sm text-slate-500 mt-1">SKU: {{ $product->code }}</p></div><a class="serial-btn serial-secondary" href="{{ route('products.catalog.index') }}">Products</a></div>
<section class="serial-card p-0">@include('products.partials.opening-stock-form')</section>
@endsection
@push('scripts')
<script>
AppPage.ready(()=>{
 const form=document.querySelector('.opening-stock-form');
 const money=value=>Number(value||0).toLocaleString('en-LK',{minimumFractionDigits:2,maximumFractionDigits:2});
 const updateTotals=()=>{
  form?.querySelectorAll('.opening-location').forEach(location=>{
   let total=0;
   location.querySelectorAll('.opening-stock-row').forEach(row=>{
    const subtotal=(Number(row.querySelector('.opening-quantity')?.value)||0)*(Number(row.querySelector('.opening-unit-cost')?.value)||0);
    row.querySelector('.opening-subtotal').textContent=money(subtotal);
    total+=subtotal;
   });
   location.querySelector('.opening-location-total').textContent='Rs '+money(total);
  });
 };
 form?.addEventListener('input',event=>{if(event.target.matches('.opening-quantity,.opening-unit-cost'))updateTotals();});
 form?.addEventListener('focusin',event=>{if(event.target.matches('.opening-quantity')&&Number(event.target.value)===0)event.target.value='';});
 form?.addEventListener('focusout',event=>{if(event.target.matches('.opening-quantity')&&event.target.value.trim()===''){event.target.value='0';updateTotals();}});
 updateTotals();
 @if(session('success'))
 const toast=document.getElementById('opening-stock-toast');
 document.getElementById('opening-stock-toast-message').textContent=@json(session('success'));
 toast.classList.remove('hidden');
 window.setTimeout(()=>toast.classList.add('hidden'),7000);
 @endif
});
</script>
@endpush
