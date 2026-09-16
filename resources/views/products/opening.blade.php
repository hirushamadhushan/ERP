@extends('layouts.app')
@section('title','Opening Stock')
@section('content')
@include('serials.common')
<div class="flex flex-wrap justify-between gap-3 mb-6"><div><h1 class="text-xl font-bold">Opening Stock — {{ $product->name }}</h1><p class="text-sm text-slate-500 mt-1">SKU: {{ $product->code }}</p></div><a class="serial-btn serial-secondary" href="{{ route('products.catalog.index') }}">Products</a></div>
<section class="serial-card">
@if(!$product->manage_stock)
<p>Stock management is disabled for this product.</p>
@elseif($product->enable_serial)
<h2>Add individual serial numbers</h2><p class="text-slate-500 mb-5">Each available serial represents one item. Import existing serials from Excel or generate new serials; do not add a separate opening quantity for the same items.</p>
<a class="serial-btn" href="{{ route('products.serials.index',['product_id'=>$product->id,'generate'=>1]) }}">Generate serial numbers</a><a class="serial-btn serial-secondary" href="{{ route('products.serials.index',['product_id'=>$product->id]).'#import' }}">Import existing serials</a>
@else
<p class="text-sm text-slate-500 mb-5">Set the starting quantity at each assigned location. Saving replaces the opening quantity; it does not add to it.</p>
<form method="POST" action="{{ route('products.catalog.opening.store',$product) }}">@csrf
<div class="serial-grid">@foreach($product->locations as $location)<div><label for="quantity-{{ $location->id }}">{{ $location->name }} ({{ $location->code }})</label><input id="quantity-{{ $location->id }}" type="number" min="0" max="999999999" step="{{ $product->unit?->allow_decimal ? '0.0001' : '1' }}" name="quantities[{{ $location->id }}]" value="{{ old('quantities.'.$location->id,$location->pivot->opening_quantity) }}" required></div>@endforeach</div>
<button class="serial-btn mt-6">Save opening stock</button></form>
@endif
</section>
@endsection

