@extends('layouts.app')
@section('title', 'Unloading UI')
@section('content')
@include('serials.common')

<div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl p-5 text-white shadow-md" style="background:linear-gradient(110deg,#7c3aed,#4f46e5);color:#fff">
    <div class="flex items-center gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-xl font-black" style="background:rgba(255,255,255,.22)">3</span>
        <div>
            <h1 class="text-2xl font-extrabold">Unloading UI</h1>
            <p class="text-sm text-white/90">Confirm customer delivery or unload vehicle stock to a warehouse</p>
        </div>
    </div>
    @if(auth()->user()->canUseDelivery('delivery.transfer'))
        <a href="{{ route('delivery.transfers.create', ['direction' => 'unloading', 'vehicle_id' => request('vehicle_id')]) }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-bold text-violet-700 shadow-sm hover:bg-violet-50">
            <i class="bi bi-box-arrow-down"></i> Unload vehicle to warehouse
        </a>
    @endif
</div>

<section class="serial-card mb-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div>
            <h2 class="!mb-0">Unloading & Proof of Delivery</h2>
            <p class="text-xs text-slate-500">Delivery / Unloading / Select a delivery</p>
        </div>
        <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-700">Awaiting delivery</span>
    </div>

    <label for="delivery_id">Delivery record</label>
    <select id="delivery_id" class="mt-1 w-full rounded-xl border border-slate-300 p-3 text-sm" onchange="if(this.value) window.location.href=this.value">
        <option value="">Select a loaded or in-transit delivery</option>
        @foreach($consignments as $consignment)
            <option value="{{ route('delivery.unloading', ['delivery_id' => $consignment->id, 'vehicle_id' => request('vehicle_id')]) }}">
                {{ $consignment->number }} · {{ $consignment->customer->name }} · {{ $consignment->loadingTransfer->vehicle->number }} · {{ ucwords(str_replace('_', ' ', $consignment->status)) }}
            </option>
        @endforeach
    </select>
    @if($consignments->isEmpty())
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
            <span>No loaded or in-transit customer delivery is available. Create one from a completed loading transfer.</span>
            <a href="{{ route('delivery.consignments.create') }}" class="serial-btn">Create delivery</a>
        </div>
    @endif

    <div class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
        @foreach(['Delivery Note No.','Order reference','Customer','Delivery Location','Arrival Time','Delivery Status'] as $label)
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <span class="block text-xs font-semibold text-slate-400">{{ $label }}</span>
                <strong class="mt-1 block text-slate-500">—</strong>
            </div>
        @endforeach
        <div><label>Receiver Name</label><input disabled placeholder="Select a delivery first" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5"></div>
        <div><label>Contact No.</label><input disabled placeholder="Select a delivery first" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5"></div>
    </div>
</section>

<section class="serial-card mb-5">
    <div class="mb-3 border-b border-slate-100 pb-3"><h2 class="!mb-0">Received Items</h2><p class="text-xs text-slate-500">Received, short, damaged and returned quantities appear after selecting a delivery.</p></div>
    <div class="overflow-x-auto rounded-xl border border-slate-200">
        <table class="serial-table w-full">
            <thead><tr><th>Product</th><th>Loaded Qty</th><th>Received Qty</th><th>Short Qty</th><th>Damaged Qty</th><th>Return Qty</th><th>Remarks</th></tr></thead>
            <tbody><tr><td colspan="7" class="py-8 text-center text-slate-400">Select a delivery record to load its items.</td></tr></tbody>
        </table>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        <button disabled class="rounded-xl bg-emerald-200 px-4 py-2.5 text-xs font-bold text-white">Confirm Received</button>
        <button disabled class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-400">Record Damage</button>
        <button disabled class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-400">Record Return</button>
        <button disabled class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-400">Capture Photo</button>
    </div>
</section>

<div class="mb-5 grid gap-5 lg:grid-cols-2">
    <section class="serial-card">
        <h3 class="mb-3 font-bold">Receiver Signature</h3>
        <div class="flex h-36 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-400">Select a delivery to capture signature</div>
    </section>
    <section class="serial-card">
        <h3 class="mb-3 font-bold">Proof of Delivery Photos</h3>
        <div class="flex h-36 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-400"><i class="bi bi-plus-lg mr-2"></i> Add photos after selecting a delivery</div>
    </section>
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
    @foreach([['Delivered','emerald'],['Partial / Short','blue'],['Damaged','amber'],['Returned','rose']] as [$label,$colour])
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><span class="text-xs text-slate-500">{{ $label }}</span><strong class="mt-1 block text-xl">0</strong><span class="text-xs text-slate-400">units</span></div>
    @endforeach
    <button disabled class="rounded-2xl bg-blue-300 p-4 font-bold text-white">Submit POD</button>
</div>

<div class="mt-4">{{ $consignments->links() }}</div>
@endsection
