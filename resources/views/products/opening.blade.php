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
 @if(session('success'))
 const toast=document.getElementById('opening-stock-toast');
 document.getElementById('opening-stock-toast-message').textContent=@json(session('success'));
 toast.classList.remove('hidden');
 window.setTimeout(()=>toast.classList.add('hidden'),4000);
 @endif
});
</script>
@endpush
