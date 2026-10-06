@extends('layouts.app')
@section('title', $consignment->number)
@section('content')
@include('serials.common')

{{-- Hero Header Banner --}}
<div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl p-5 text-white shadow-md" style="background: linear-gradient(110deg, #6d28d9, #4f46e5);">
    <div class="flex items-center gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-xl font-black shadow-inner" style="background:rgba(255,255,255,0.22)">
            3
        </span>
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Unloading</h1>
            <p class="text-sm text-white/90">Confirm delivery, record discrepancies and capture POD</p>
        </div>
    </div>
    <div class="no-print flex gap-2">
        <a class="rounded-xl bg-white/20 px-4 py-2 text-xs font-bold text-white hover:bg-white/30 transition-all" href="{{ route('delivery.unloading') }}">Unloading</a>
        @if(auth()->user()->canUseDelivery('delivery.transfer'))
            <a class="rounded-xl bg-white/20 px-4 py-2 text-xs font-bold text-white hover:bg-white/30 transition-all" href="{{ route('delivery.transfers.create', ['direction' => 'unloading', 'vehicle_id' => $consignment->loadingTransfer->vehicle_id]) }}"><i class="bi bi-box-arrow-down"></i> Unload to warehouse</a>
        @endif
        <button type="button" onclick="window.print()" class="rounded-xl bg-white px-4 py-2 text-xs font-bold text-violet-700 shadow-sm hover:bg-violet-50 transition-all">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
</div>

@if($consignment->status === 'loaded' && auth()->user()->canUseDelivery('delivery.transfer'))
<form class="mb-5" method="POST" action="{{ route('delivery.consignments.depart', $consignment) }}">@csrf<button class="serial-btn">Mark in transit</button></form>
@elseif($consignment->status === 'in_transit' && ! $consignment->arrived_at && auth()->user()->canUseDelivery('delivery.transfer'))
<form class="mb-5 rounded-xl border border-violet-200 bg-violet-50 p-4" method="POST" action="{{ route('delivery.consignments.arrive', $consignment) }}">@csrf
<p class="mb-2 text-sm text-violet-900">Record arrival at the customer before confirming receipt.</p><button class="serial-btn">Mark arrived at customer</button></form>
@endif

{{-- Form wrapper for complete delivery & proof of delivery --}}
<form id="unloading-pod-form" method="POST" enctype="multipart/form-data" action="{{ route('delivery.consignments.complete', $consignment) }}">
    @csrf

    {{-- Section 1: Unloading & Proof of Delivery Details Card --}}
    <section class="serial-card mb-6 shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-lg font-bold text-slate-800 !mb-0">Unloading & Proof of Delivery</h2>
                <p class="text-xs font-medium text-slate-400">
                    Delivery / Unloading / <span class="font-bold text-slate-600">{{ $consignment->number }}</span>
                </p>
            </div>
            <span class="rounded-full px-3.5 py-1 text-xs font-bold bg-purple-100 text-purple-800">
                {{ ucwords(str_replace('_', ' ', $consignment->status)) }}
            </span>
        </div>

        <div class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            {{-- Delivery Note No --}}
            <div>
                <span class="text-xs font-semibold text-slate-400 block mb-1">Delivery Note No.</span>
                <strong class="text-sm font-extrabold text-slate-800">{{ $consignment->number }}</strong>
            </div>

            {{-- Sales Order --}}
            <div>
                <span class="text-xs font-semibold text-slate-400 block mb-1">Order reference</span>
                <strong class="text-sm font-extrabold text-blue-600">
                    {{ $consignment->sales_order_reference ?: 'Not provided' }}
                </strong>
            </div>

            {{-- Customer --}}
            <div>
                <span class="text-xs font-semibold text-slate-400 block mb-1">Customer</span>
                <strong class="text-sm font-extrabold text-slate-800">{{ $consignment->customer->name }}</strong>
            </div>

            {{-- Delivery Location --}}
            <div class="sm:col-span-2">
                <span class="text-xs font-semibold text-slate-400 block mb-1">Delivery Location</span>
                <strong class="text-xs font-semibold text-slate-700 leading-relaxed block">{{ $consignment->delivery_address }}</strong>
            </div>

            {{-- Arrival Time --}}
            <div>
                <span class="text-xs font-semibold text-slate-400 block mb-1">Arrival Time</span>
                <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                    <i class="bi bi-calendar-event text-blue-600"></i>
                    <span>{{ $consignment->arrived_at?->format('d M Y h:i A') ?: 'Not recorded' }}</span>
                </div>
            </div>

            {{-- Receiver Name --}}
            <div>
                <label for="receiver_name" class="text-xs font-semibold text-slate-600 block mb-1">Receiver Name *</label>
                @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.transfer'))
                    <input id="receiver_name" name="receiver_name" required maxlength="150" value="{{ old('receiver_name', $consignment->receiver_name) }}" placeholder="Receiver name" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-purple-500">
                @else
                    <strong class="text-xs font-bold text-slate-800 block p-2 bg-slate-50 rounded-xl border border-slate-200">{{ $consignment->receiver_name ?: '—' }}</strong>
                @endif
            </div>

            {{-- Contact No --}}
            <div>
                <label for="receiver_phone" class="text-xs font-semibold text-slate-600 block mb-1">Contact No.</label>
                @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.transfer'))
                    <div class="flex items-center gap-2">
                        <input id="receiver_phone" name="receiver_phone" maxlength="40" value="{{ old('receiver_phone', $consignment->receiver_phone) }}" placeholder="Receiver phone" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-purple-500">
                    </div>
                @else
                    <div class="flex items-center justify-between p-2 bg-slate-50 rounded-xl border border-slate-200">
                        <strong class="text-xs font-bold text-slate-800">{{ $consignment->receiver_phone ?: '—' }}</strong>
                        @if($consignment->receiver_phone)
                        <a href="tel:{{ $consignment->receiver_phone }}" class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                            <i class="bi bi-telephone-fill text-[10px]"></i>
                        </a>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Delivery Status --}}
            <div>
                <span class="text-xs font-semibold text-slate-400 block mb-1">Delivery Status</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                    <i class="bi bi-check-lg"></i> {{ $consignment->status === 'arrived' ? 'Receiving' : ucwords(str_replace('_', ' ', $consignment->status)) }}
                </span>
            </div>
        </div>
    </section>

    {{-- Section 2: Received Items Table Card --}}
    <section class="serial-card mb-6 shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-lg font-bold text-slate-800 !mb-0">Received Items</h2>
                <p class="text-xs text-slate-400">Record received, missing (short), damaged and returned quantities.</p>
            </div>
            <span class="rounded-full bg-purple-100 px-3.5 py-1 text-xs font-bold text-purple-800">{{ $consignment->lines->count() }} line items</span>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="serial-table w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-700 border-b border-slate-200">
                        <th class="py-3 px-3 font-bold">Product</th>
                        <th class="py-3 px-3 font-bold text-center">Loaded Qty</th>
                        <th class="py-3 px-3 font-bold text-center bg-blue-50 text-blue-900 border-x border-blue-100">Received Qty</th>
                        <th class="py-3 px-3 font-bold text-center">Short Qty</th>
                        <th class="py-3 px-3 font-bold text-center">Damaged Qty</th>
                        <th class="py-3 px-3 font-bold text-center">Return Qty</th>
                        <th class="py-3 px-3 font-bold">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach($consignment->lines as $index => $line)
                        @php($source = $line->transferLine)
                        @php($unit = $source->stockItem->product->unit?->short_name ?? 'Ctn')
                        @php($loaded = $source->quantity)
                        @php($isEditable = $consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.transfer'))

                        <tr class="hover:bg-slate-50 transition-colors line-row" data-loaded="{{ $loaded }}">
                            <td class="py-3 px-3 font-bold text-slate-800">
                                {{ $source->stockItem->product->name }}
                                @if($source->stockItem->variant->isNotEmpty())
                                    <span class="text-slate-500 font-normal"> / {{ $source->stockItem->variant->first()->value }}</span>
                                @endif
                                @foreach($source->lots as $lot)
                                    <span class="block text-[11px] font-normal text-slate-400">Lot: {{ $lot->lot_number }}</span>
                                @endforeach
                                @foreach($source->serials as $serial)
                                    <span class="block text-[11px] font-normal text-slate-400">Serial: {{ $serial->serial_number }}</span>
                                @endforeach
                            </td>

                            <td class="py-3 px-3 text-center font-extrabold text-slate-700">
                                {{ $loaded }}
                            </td>

                            {{-- Received Qty (Delivered) --}}
                            <td class="py-2 px-3 text-center bg-blue-50/50 border-x border-blue-100">
                                @if($isEditable)
                                    <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
                                    <input type="number" name="lines[{{ $index }}][delivered]" required min="0" max="{{ $loaded }}" step="{{ $source->stockItem->product->unit?->allow_decimal ? '0.0001' : '1' }}" value="{{ old('lines.'.$index.'.delivered', $line->delivered_quantity ?? $loaded) }}" class="w-20 text-center rounded-lg bg-blue-100 border border-blue-300 font-extrabold text-blue-900 py-1 text-xs focus:ring-2 focus:ring-blue-500 qty-input input-delivered">
                                @else
                                    <strong class="font-extrabold text-blue-800">{{ $line->delivered_quantity }}</strong>
                                @endif
                            </td>

                            {{-- Short Qty (Missing) --}}
                            <td class="py-2 px-3 text-center">
                                @if($isEditable)
                                    <input type="number" name="lines[{{ $index }}][missing]" required min="0" max="{{ $loaded }}" step="{{ $source->stockItem->product->unit?->allow_decimal ? '0.0001' : '1' }}" value="{{ old('lines.'.$index.'.missing', $line->missing_quantity ?? 0) }}" class="w-16 text-center rounded-lg border border-slate-300 font-bold text-slate-800 py-1 text-xs focus:ring-2 focus:ring-rose-500 qty-input input-missing">
                                @else
                                    <span class="{{ $line->missing_quantity > 0 ? 'text-rose-600 font-extrabold' : 'text-slate-600 font-bold' }}">
                                        {{ $line->missing_quantity }}
                                    </span>
                                @endif
                            </td>

                            {{-- Damaged Qty --}}
                            <td class="py-2 px-3 text-center">
                                @if($isEditable)
                                    <input type="number" name="lines[{{ $index }}][damaged]" required min="0" max="{{ $loaded }}" step="{{ $source->stockItem->product->unit?->allow_decimal ? '0.0001' : '1' }}" value="{{ old('lines.'.$index.'.damaged', $line->damaged_quantity ?? 0) }}" class="w-16 text-center rounded-lg border border-slate-300 font-bold text-slate-800 py-1 text-xs focus:ring-2 focus:ring-rose-500 qty-input input-damaged">
                                @else
                                    <span class="{{ $line->damaged_quantity > 0 ? 'text-rose-600 font-extrabold bg-rose-50 px-2 py-0.5 rounded-md' : 'text-slate-600 font-bold' }}">
                                        {{ $line->damaged_quantity }}
                                    </span>
                                @endif
                            </td>

                            {{-- Return Qty --}}
                            <td class="py-2 px-3 text-center">
                                @if($isEditable)
                                    <input type="number" name="lines[{{ $index }}][return]" required min="0" max="{{ $loaded }}" step="{{ $source->stockItem->product->unit?->allow_decimal ? '0.0001' : '1' }}" value="{{ old('lines.'.$index.'.return', $line->return_quantity ?? 0) }}" class="w-16 text-center rounded-lg border border-slate-300 font-bold text-slate-800 py-1 text-xs focus:ring-2 focus:ring-amber-500 qty-input input-return">
                                @else
                                    <span class="{{ $line->return_quantity > 0 ? 'text-amber-600 font-extrabold' : 'text-slate-600 font-bold' }}">
                                        {{ $line->return_quantity }}
                                    </span>
                                @endif
                            </td>

                            {{-- Remarks --}}
                            <td class="py-2 px-3">
                                @if($isEditable)
                                    <input type="text" name="lines[{{ $index }}][remarks]" placeholder="e.g. 5 ctn wet" maxlength="500" value="{{ old('lines.'.$index.'.remarks', $line->remarks) }}" class="w-full rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-700">
                                @else
<span class="text-slate-500 font-medium text-[11px]">{{ $line->remarks ?: "-" }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Discrepancy Action Buttons Toolbar --}}
        @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.transfer'))
        <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-slate-100 pt-3">
            <button type="button" id="btn-all-received" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-all">
                <i class="bi bi-check-circle-fill text-sm"></i> Confirm Received
            </button>
            <button type="button" id="btn-record-damage" class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-all">
                <i class="bi bi-exclamation-triangle-fill text-sm text-amber-600"></i> Record Damage
            </button>
            <button type="button" id="btn-create-return" class="inline-flex items-center gap-2 rounded-xl border border-rose-300 bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-800 hover:bg-rose-100 transition-all">
                <i class="bi bi-box-arrow-down-left text-sm text-rose-600"></i> Create Return
            </button>
            <button type="button" id="btn-capture-photo" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition-all">
                <i class="bi bi-camera-fill text-sm text-slate-500"></i> Capture Photo
            </button>
        </div>
        @endif
    </section>

    {{-- Section 3: Signature & Proof of Delivery Photos Cards --}}
    <div class="mb-6 grid gap-5 lg:grid-cols-2">
        {{-- Receiver Signature Card --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-bold text-slate-800">Receiver Signature</h3>
                    <button type="button" id="clear-sig-btn" class="text-xs font-bold text-blue-600 hover:underline">Clear</button>
                </div>

                @php($existingSig = $consignment->proofs->firstWhere('kind', 'signature'))
                @if($existingSig)
                    <div class="mb-3 rounded-xl border border-slate-200 bg-slate-50 p-2 flex items-center justify-center">
                        <img src="{{ route('delivery.consignments.proofs.show', [$consignment, $existingSig]) }}" alt="Receiver Signature" class="max-h-32 object-contain">
                    </div>
                @else
                    <div class="relative rounded-xl border border-slate-300 bg-slate-50/50 p-1 mb-2">
                        <canvas id="sig-canvas" width="600" height="160" class="w-full h-36 rounded-lg bg-white touch-none cursor-crosshair border border-slate-200"></canvas>
                        <input id="signature_data" type="hidden" name="signature_data">
                    </div>
                @endif
            </div>

            <div class="mt-2 text-[11px] text-slate-500 font-medium">
                Signed by: <strong id="sig-signed-by" class="text-slate-800 font-bold">{{ $consignment->receiver_name }}</strong>
                <span class="block text-slate-400">{{ $consignment->completed_at?->format('d M Y h:i A') ?: 'Not submitted' }}</span>
            </div>
        </div>

        {{-- Proof of Delivery Photos Card --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                <h3 class="text-sm font-bold text-slate-800">Proof of Delivery Photos</h3>
                <span class="text-xs text-slate-400 font-semibold">{{ $consignment->proofs->where('kind', 'photo')->count() }} attached</span>
            </div>

            <div class="grid grid-cols-3 gap-3" id="photo-thumbnails-container">
                @foreach($consignment->proofs->where('kind', 'photo') as $photoProof)
                    <a href="{{ route('delivery.consignments.proofs.show', [$consignment, $photoProof]) }}" target="_blank" class="group relative aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-100">
                        <img src="{{ route('delivery.consignments.proofs.show', [$consignment, $photoProof]) }}" alt="Proof Photo" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-200">
                    </a>
                @endforeach

                {{-- Add Photo Button Box --}}
                @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.transfer'))
                <label for="photos-input" class="flex flex-col items-center justify-center aspect-square rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/50 hover:bg-slate-100 hover:border-blue-400 cursor-pointer transition-all">
                    <i class="bi bi-plus-lg text-2xl text-slate-400"></i>
                    <span class="mt-1 text-xs font-bold text-slate-600">Add Photo</span>
                    <input id="photos-input" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" capture="environment" class="hidden">
                </label>
                @endif
            </div>
        </div>
    </div>

    {{-- Section 4: Notes --}}
    <div class="mb-6 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <label for="proof_notes" class="text-xs font-semibold text-slate-600 mb-1 block">Proof / Discrepancy Notes</label>
        @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.transfer'))
            <textarea id="proof_notes" name="proof_notes" rows="2" maxlength="2000" placeholder="Add optional delivery notes or discrepancy details..." class="w-full rounded-xl border border-slate-300 p-3 text-xs text-slate-800 focus:ring-2 focus:ring-purple-500">{{ old('proof_notes', $consignment->proof_notes) }}</textarea>
        @else
            <p class="text-xs text-slate-700 font-medium p-2 bg-slate-50 rounded-xl border border-slate-200">{{ $consignment->proof_notes ?: 'No discrepancy notes.' }}</p>
        @endif
    </div>

    {{-- Section 5: Delivery Summary Cards & Submit POD Bar --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-12">
        {{-- Stat Summary Cards (4 Cards) --}}
        <div class="lg:col-span-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
            {{-- Delivered --}}
            <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="bi bi-check-circle-fill text-lg"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 block">Delivered</span>
                    <strong id="stat-delivered" class="text-base font-black text-slate-800">0</strong>
                    <span class="text-[10px] text-slate-400 block">units</span>
                </div>
            </div>

            {{-- Partial --}}
            <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="bi bi-clock-history text-lg"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 block">Partial / Short</span>
                    <strong id="stat-partial" class="text-base font-black text-slate-800">0</strong>
                    <span class="text-[10px] text-slate-400 block">units</span>
                </div>
            </div>

            {{-- Damaged --}}
            <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="bi bi-exclamation-triangle-fill text-lg"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 block">Damaged</span>
                    <strong id="stat-damaged" class="text-base font-black text-slate-800">0</strong>
                    <span class="text-[10px] text-slate-400 block">units</span>
                </div>
            </div>

            {{-- Returned --}}
            <div class="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <i class="bi bi-box-arrow-down-left text-lg"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 block">Returned</span>
                    <strong id="stat-returned" class="text-base font-black text-slate-800">0</strong>
                    <span class="text-[10px] text-slate-400 block">units</span>
                </div>
            </div>
        </div>

        {{-- Submit POD Main Action Button --}}
        <div class="lg:col-span-4 flex items-stretch">
            @if($consignment->status === 'arrived' && $consignment->arrived_at && auth()->user()->canUseDelivery('delivery.transfer'))
                <button type="submit" id="submit-pod-btn" class="w-full flex items-center justify-center gap-3 rounded-2xl bg-blue-600 px-6 py-4 text-sm font-extrabold text-white shadow-lg hover:bg-blue-700 transition-all">
                    <i class="bi bi-send-fill text-lg"></i>
                    Submit POD
                </button>
            @else
                <div class="w-full flex items-center justify-center gap-2 rounded-2xl bg-slate-100 p-4 text-xs font-bold text-slate-500 border border-slate-200">
                    <i class="bi bi-check-circle-fill text-emerald-600 text-base"></i>
                    {{ in_array($consignment->status, ['loaded','in_transit'], true) ? 'Complete the previous step to submit POD' : 'POD Completed' }}
                </div>
            @endif
        </div>
    </div>
</form>

@if($consignment->returnNote)
<section class="serial-card mb-6 border border-rose-200 bg-rose-50">
<h2>Return note {{ $consignment->returnNote->number }} · {{ ucfirst($consignment->returnNote->status) }}</h2>
<p class="text-sm text-slate-700">{{ $consignment->returnNote->lines->count() }} returned item(s). @if($consignment->returnNote->status === 'pending')These remain on the vehicle until you unload them and link the matching warehouse transfer below.@else Warehouse unloading recorded. @endif</p>
@if($consignment->returnNote->status === 'pending' && auth()->user()->canUseDelivery('delivery.transfer'))
    <a class="serial-btn mt-3" href="{{ route('delivery.transfers.create', ['return_id' => $consignment->returnNote->id]) }}">Unload returned stock</a>
    <p class="mt-2 text-xs text-slate-500">The return lines will be filled and locked automatically. Choose the receiving warehouse and confirm.</p>
@endif
</section>
@endif

{{-- Timeline --}}
<section class="serial-card shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
    <h3 class="text-sm font-bold text-slate-800 mb-3 border-b border-slate-100 pb-2">Delivery Timeline</h3>
    <ol class="text-xs space-y-2 font-medium text-slate-600">
        @foreach($consignment->events->sortBy('created_at') as $event)
            <li class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-purple-600"></span>
                <strong class="text-slate-800">{{ ucwords(str_replace('_', ' ', $event->event)) }}</strong>
                <span class="text-slate-400">· {{ $event->created_at->format('Y-m-d H:i') }} · {{ $event->user?->name }}</span>
            </li>
        @endforeach
    </ol>
</section>
@endsection

@push('scripts')
<script>
AppPage.ready(() => {
    const form = document.getElementById('unloading-pod-form');
    if (!form) return;

    const rows = [...form.querySelectorAll('.line-row')];
    const receiverNameInput = document.getElementById('receiver_name');
    const sigSignedBy = document.getElementById('sig-signed-by');

    if (receiverNameInput && sigSignedBy) {
        receiverNameInput.addEventListener('input', () => {
            sigSignedBy.textContent = receiverNameInput.value.trim() || 'Receiver';
        });
    }

    // Live calculation of totals and card stats
    function recalculateTotals() {
        let totalDelivered = 0;
        let totalDamaged = 0;
        let totalMissing = 0;
        let totalReturned = 0;

        rows.forEach(row => {
            const loaded = parseFloat(row.dataset.loaded || '0') || 0;
            const delInput = row.querySelector('.input-delivered');
            const damInput = row.querySelector('.input-damaged');
            const misInput = row.querySelector('.input-missing');
            const retInput = row.querySelector('.input-return');

            const del = parseFloat(delInput?.value || '0') || 0;
            const dam = parseFloat(damInput?.value || '0') || 0;
            const mis = parseFloat(misInput?.value || '0') || 0;
            const ret = parseFloat(retInput?.value || '0') || 0;

            totalDelivered += del;
            totalDamaged += dam;
            totalMissing += mis;
            totalReturned += ret;
        });

        document.getElementById('stat-delivered').textContent = totalDelivered;
        document.getElementById('stat-partial').textContent = totalMissing;
        document.getElementById('stat-damaged').textContent = totalDamaged;
        document.getElementById('stat-returned').textContent = totalReturned;
    }

    form.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', recalculateTotals);
    });
    recalculateTotals();

    // Confirm Received Quick Button
    document.getElementById('btn-all-received')?.addEventListener('click', () => {
        rows.forEach(row => {
            const loaded = row.dataset.loaded || '0';
            const del = row.querySelector('.input-delivered');
            const dam = row.querySelector('.input-damaged');
            const mis = row.querySelector('.input-missing');
            const ret = row.querySelector('.input-return');
            if (del) del.value = loaded;
            if (dam) dam.value = 0;
            if (mis) mis.value = 0;
            if (ret) ret.value = 0;
        });
        recalculateTotals();
    });

    // Record Damage Quick Button
    document.getElementById('btn-record-damage')?.addEventListener('click', () => {
        const firstRow = rows[0];
        if (firstRow) {
            const damInput = firstRow.querySelector('.input-damaged');
            if (damInput) {
                damInput.focus();
                damInput.select();
            }
        }
    });

    // Create Return Quick Button
    document.getElementById('btn-create-return')?.addEventListener('click', () => {
        const firstRow = rows[0];
        if (firstRow) {
            const retInput = firstRow.querySelector('.input-return');
            if (retInput) {
                retInput.focus();
                retInput.select();
            }
        }
    });

    // Capture Photo Quick Button
    const photosInput = document.getElementById('photos-input');
    document.getElementById('btn-capture-photo')?.addEventListener('click', () => {
        photosInput?.click();
    });

    // Handle Photo Input Previews
    const thumbnailsContainer = document.getElementById('photo-thumbnails-container');
    photosInput?.addEventListener('change', (e) => {
        const files = [...e.target.files];
        files.forEach(file => {
            const reader = new FileReader();
            reader.onload = (event) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'relative aspect-square rounded-xl overflow-hidden border border-blue-300 bg-slate-100 shadow-xs';
                wrapper.innerHTML = `<img src="${event.target.result}" class="h-full w-full object-cover">`;
                thumbnailsContainer.insertBefore(wrapper, thumbnailsContainer.querySelector('label'));
            };
            reader.readAsDataURL(file);
        });
    });

    // Signature Canvas Pad
    const canvas = document.getElementById('sig-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        const sigHiddenInput = document.getElementById('signature_data');
        let isDrawing = false;
        let hasSigned = false;

        const getPos = (e) => {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: (clientX - rect.left) * (canvas.width / rect.width),
                y: (clientY - rect.top) * (canvas.height / rect.height)
            };
        };

        const startDrawing = (e) => {
            isDrawing = true;
            hasSigned = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        };

        const draw = (e) => {
            if (!isDrawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.strokeStyle = '#1e1b4b';
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();
            e.preventDefault();
        };

        const stopDrawing = () => {
            if (isDrawing) {
                isDrawing = false;
                sigHiddenInput.value = canvas.toDataURL('image/png');
            }
        };

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseleave', stopDrawing);

        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        document.getElementById('clear-sig-btn')?.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasSigned = false;
            sigHiddenInput.value = '';
        });
    }

    // Form submit validation
    form.addEventListener('submit', (e) => {
        let valid = true;
        rows.forEach(row => {
            const loaded = parseFloat(row.dataset.loaded || '0') || 0;
            const del = parseFloat(row.querySelector('.input-delivered')?.value || '0') || 0;
            const dam = parseFloat(row.querySelector('.input-damaged')?.value || '0') || 0;
            const mis = parseFloat(row.querySelector('.input-missing')?.value || '0') || 0;
            const ret = parseFloat(row.querySelector('.input-return')?.value || '0') || 0;

            const sum = del + dam + mis + ret;
            if (Math.abs(sum - loaded) > 0.0001) {
                valid = false;
                row.classList.add('bg-rose-50');
            } else {
                row.classList.remove('bg-rose-50');
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('For every item row, Delivered + Damaged + Short + Return must equal Loaded quantity.');
        } else {
            document.getElementById('submit-pod-btn').disabled = true;
        }
    });
});
</script>
@endpush
