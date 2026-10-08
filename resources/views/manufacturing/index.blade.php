@extends('layouts.app')
@section('title','Manufacturing')
@section('content')
@include('serials.common')
<style>
.mfg-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:20px}
.mfg-kpi{position:relative;overflow:hidden;min-height:112px;padding:18px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 5px 16px rgba(15,23,42,.06)}
.mfg-kpi::after{content:"";position:absolute;right:-22px;bottom:-28px;width:88px;height:88px;border-radius:50%;background:var(--soft)}
.mfg-kpi-head{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#64748b;font-size:12px;font-weight:700}
.mfg-kpi-icon{display:grid;width:34px;height:34px;place-items:center;border-radius:10px;background:var(--soft);color:var(--accent);font-size:16px}
.mfg-kpi-value{position:relative;z-index:1;margin-top:8px;color:#0f172a;font-size:27px;font-weight:900;line-height:1}
@media(max-width:1100px){.mfg-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:640px){.mfg-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.mfg-kpi{min-height:98px;padding:14px}.mfg-kpis .mfg-kpi:last-child{grid-column:1/-1}}
</style>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl p-6 shadow-xl" style="background:linear-gradient(110deg,#0f172a,#312e81,#6b21a8);color:#fff"><div><p class="text-xs font-bold uppercase tracking-widest" style="color:#a7f3d0">Production</p><h1 class="text-2xl font-black" style="color:#fff">Manufacturing</h1><p class="text-sm" style="color:#dbeafe">Plan recipes, control production stages and create traceable finished-product lots.</p></div><div class="flex gap-2"><a class="rounded-xl px-4 py-3 text-sm font-black text-white" style="background:rgba(255,255,255,.14)" href="{{ route('manufacturing.boms.index') }}">Recipes / BoMs</a>@if(auth()->user()->canUse('manufacturing.process'))<a class="rounded-xl px-5 py-3 text-sm font-black shadow-sm" style="background:#fff;color:#7e22ce" href="{{ route('manufacturing.create') }}"><i class="bi bi-plus-circle"></i> New manufacturing order</a>@endif</div></div>
@php($kpis=[
 ['key'=>'draft','label'=>'Draft','icon'=>'bi-file-earmark-text','accent'=>'#7c3aed','soft'=>'#ede9fe'],
 ['key'=>'confirmed','label'=>'Confirmed','icon'=>'bi-patch-check','accent'=>'#2563eb','soft'=>'#dbeafe'],
 ['key'=>'in_progress','label'=>'In Production','icon'=>'bi-gear-wide-connected','accent'=>'#d97706','soft'=>'#fef3c7'],
 ['key'=>'completed','label'=>'Completed','icon'=>'bi-check-circle','accent'=>'#059669','soft'=>'#d1fae5'],
 ['key'=>'cancelled','label'=>'Cancelled','icon'=>'bi-x-circle','accent'=>'#e11d48','soft'=>'#ffe4e6'],
])
<div class="mfg-kpis">@foreach($kpis as $kpi)<div class="mfg-kpi" style="--accent:{{ $kpi['accent'] }};--soft:{{ $kpi['soft'] }}"><div class="mfg-kpi-head"><span>{{ $kpi['label'] }}</span><span class="mfg-kpi-icon"><i class="bi {{ $kpi['icon'] }}"></i></span></div><div class="mfg-kpi-value">{{ $counts[$kpi['key']]??0 }}</div></div>@endforeach</div>
<section class="serial-card"><h2>Manufacturing orders</h2><div class="overflow-x-auto"><table class="serial-table"><thead><tr><th>Order</th><th>Created</th><th>Finished product</th><th>Warehouse</th><th>Quantity</th><th>Status</th><th>Output lot</th><th>Total cost</th></tr></thead><tbody>@forelse($orders as $order)<tr><td><a class="font-bold text-purple-700" href="{{ route('manufacturing.show',$order) }}">{{ $order->number }}</a></td><td>{{ $order->created_at->format('Y-m-d H:i') }}</td><td>{{ $order->product->name }}</td><td>{{ $order->location->name }}</td><td>{{ number_format($order->quantity,$order->product->unit?->allow_decimal?4:0) }} {{ $order->product->unit?->short_name }}</td><td><span class="rounded-full bg-purple-100 px-2 py-1 text-xs font-bold text-purple-700">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></td><td>{{ $order->outputLot?->lot_number ?: '—' }}</td><td>Rs {{ number_format($order->total_cost,2) }}</td></tr>@empty<tr><td colspan="8" class="text-center text-slate-500">No manufacturing orders yet.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $orders->links() }}</div></section>
@endsection
