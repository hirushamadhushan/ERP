@extends('layouts.app')
@section('title','Selling Price Groups')
@section('content')
<div class="mb-5"><h1 class="text-xl font-bold">Selling Price Group Prices</h1><p class="mt-1 text-sm text-slate-500">{{ $product->name }} · {{ $product->code }}</p></div>
<form method="POST" action="{{ route('products.catalog.prices.update',$product) }}" class="rounded-2xl border border-purple-100 bg-white shadow-sm">@csrf @method('PUT')
<div class="border-b border-purple-100 px-6 py-4"><h2 class="font-bold text-purple-700">Customer group pricing</h2></div><div class="grid gap-5 p-6 md:grid-cols-2">
@forelse($groups as $group)@php $saved=$product->sellingPrices->firstWhere('selling_price_group_id',$group->id)?->selling_price; @endphp<label class="text-xs font-bold text-slate-700">{{ $group->name }}<input class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20" name="prices[{{ $group->id }}]" type="number" min="0" step="0.0001" value="{{ old('prices.'.$group->id,$saved) }}" placeholder="Default: {{ number_format($product->selling_price,2) }}"></label>@empty<p class="text-sm text-slate-500">No selling price groups have been created yet.</p>@endforelse
</div><div class="flex justify-end gap-3 border-t border-purple-100 px-6 py-4"><a href="{{ route('products.catalog.index') }}" class="rounded-xl bg-slate-100 px-5 py-2.5 text-xs font-bold">Skip</a><button class="rounded-xl bg-purple-600 px-5 py-2.5 text-xs font-bold text-white">Save prices</button></div></form>
@endsection
