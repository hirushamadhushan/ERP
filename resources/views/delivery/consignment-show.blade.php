@extends('layouts.app')
@section('title', $consignment->number)
@section('content')
@include('serials.common')
@php
    $companyLocation = $consignment->loadingTransfer->warehouse;
    $companyAddress = collect([$companyLocation->landmark, $companyLocation->city, $companyLocation->state, $companyLocation->zip_code, $companyLocation->country])->filter()->join(', ');
    $companyLogo = $businessSetting->logo_path ?? asset('images/codeza-logo.png');
    $signatureProof = $consignment->proofs->firstWhere('kind', 'signature');
    $photoProofs = $consignment->proofs->where('kind', 'photo');
    $hasOutcome = in_array($consignment->status, ['delivered', 'partial', 'failed'], true);
@endphp

{{-- Formal document identity shown only on paper/PDF. --}}
<div class="delivery-print-header print-only">
    <div class="delivery-print-brand">
        <img src="{{ $companyLogo }}" alt="{{ $businessSetting->business_name ?? 'Codeza ERP' }} logo">
        <div>
            <h1>{{ $businessSetting->business_name ?? 'Codeza ERP' }}</h1>
            <p>{{ $companyLocation->name }}{{ $companyLocation->code ? ' · '.$companyLocation->code : '' }}</p>
            @if($companyAddress)<p>{{ $companyAddress }}</p>@endif
        </div>
    </div>
    <div class="delivery-print-document">
        <span>Delivery Receipt & Proof of Delivery</span>
        <strong>{{ $consignment->number }}</strong>
        <p>Printed {{ now()->format('Y-m-d H:i') }}</p>
    </div>
</div>

{{-- Hero Header Banner --}}
<div class="no-print mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl p-5 text-white shadow-md" style="background: linear-gradient(110deg, #6d28d9, #4f46e5);">
    <div class="flex items-center gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-xl font-black shadow-inner" style="background:rgba(255,255,255,0.22)">
            3
        </span>
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Customer Delivery</h1>
            <p class="text-sm text-white/90">Confirm delivery, record discrepancies and capture proof of delivery</p>
        </div>
    </div>
    <div class="no-print flex gap-2">
        <a class="rounded-xl bg-white/20 px-4 py-2 text-xs font-bold text-white hover:bg-white/30 transition-all" href="{{ route('delivery.consignments.index') }}">Delivery Dashboard</a>
        <button type="button" onclick="window.print()" class="rounded-xl bg-white px-4 py-2 text-xs font-bold text-violet-700 shadow-sm hover:bg-violet-50 transition-all">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
</div>

@if($consignment->status === 'loaded' && auth()->user()->canUseDelivery('delivery.dispatch'))
<form class="no-print mb-5" method="POST" action="{{ route('delivery.consignments.depart', $consignment) }}">@csrf<button class="serial-btn">Mark in transit</button></form>
@elseif($consignment->status === 'in_transit' && ! $consignment->arrived_at && auth()->user()->canUseDelivery('delivery.arrive'))
<form class="no-print mb-5 rounded-xl border border-violet-200 bg-violet-50 p-4" method="POST" action="{{ route('delivery.consignments.arrive', $consignment) }}">@csrf
<p class="mb-2 text-sm text-violet-900">Record arrival at the customer before confirming receipt.</p><button class="serial-btn">Mark arrived at customer</button></form>
@endif

@if($consignment->status === 'loaded' && auth()->user()->canUseDelivery('delivery.dispatch'))
<details class="no-print mb-4 rounded-xl border border-slate-200 bg-white p-4">
    <summary class="cursor-pointer text-xs font-bold text-slate-700">Reschedule delivery</summary>
    <form method="POST" action="{{ route('delivery.consignments.reschedule', $consignment) }}" class="mt-3 grid gap-3 md:grid-cols-3">@csrf
        <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $consignment->scheduled_at?->format('Y-m-d\TH:i')) }}" required>
        <input name="reason" minlength="5" maxlength="500" placeholder="Reason for rescheduling" required>
        <button class="serial-btn">Save new schedule</button>
    </form>
</details>
@endif
@if(in_array($consignment->status, \App\Models\DeliveryConsignment::ACTIVE_STATUSES, true) && auth()->user()->canUseDelivery('delivery.correct'))
<details class="no-print mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
    <summary class="cursor-pointer text-xs font-bold text-rose-800">Cancel this delivery</summary>
<p class="mt-2 text-xs text-rose-700">Stock remains on the vehicle until an authorized unloading or a corrected delivery is recorded.</p>
    <form method="POST" action="{{ route('delivery.consignments.cancel', $consignment) }}" class="mt-3 flex flex-wrap gap-3">@csrf
        <input class="min-w-[280px] flex-1" name="cancel_reason" minlength="5" maxlength="500" placeholder="Mandatory cancellation reason" required>
        <button class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white">Cancel delivery</button>
    </form>
</details>
@endif
@if($consignment->status === 'cancelled')
<div class="mb-5 rounded-xl border border-slate-300 bg-slate-100 p-4 text-xs text-slate-700"><strong>Cancelled:</strong> {{ $consignment->cancel_reason }} · {{ $consignment->cancelled_at?->format('d M Y h:i A') }} · {{ $consignment->cancelledBy?->name }}</div>
@endif

{{-- Form wrapper for complete delivery & proof of delivery --}}
<form id="unloading-pod-form" class="{{ $hasOutcome ? 'pod-has-outcome' : 'pod-pending' }}" method="POST" enctype="multipart/form-data" action="{{ route('delivery.consignments.complete', $consignment) }}">
    @csrf

    {{-- Section 1: Unloading & Proof of Delivery Details Card --}}
    <section class="serial-card mb-6 shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-lg font-bold text-slate-800 !mb-0">Delivery Receipt & Proof</h2>
                <p class="text-xs font-medium text-slate-400">
                    Delivery / Customer receipt / <span class="font-bold text-slate-600">{{ $consignment->number }}</span>
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
                @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))
                    <input id="receiver_name" name="receiver_name" required maxlength="150" value="{{ old('receiver_name', $consignment->receiver_name) }}" placeholder="Receiver name" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-purple-500">
                @else
                    <strong class="text-xs font-bold text-slate-800 block p-2 bg-slate-50 rounded-xl border border-slate-200">{{ $consignment->receiver_name ?: '—' }}</strong>
                @endif
            </div>

            {{-- Contact No --}}
            <div>
                <label for="receiver_phone" class="text-xs font-semibold text-slate-600 block mb-1">Contact No.</label>
                @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))
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
            <div><label for="receiver_id_reference" class="text-xs font-semibold text-slate-600 block mb-1">Receiver ID / Reference</label>
                @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))<input id="receiver_id_reference" name="receiver_id_reference" maxlength="100" value="{{ old('receiver_id_reference') }}" placeholder="NIC, staff ID or reference" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs">@else<strong class="block rounded-xl border bg-slate-50 p-2 text-xs">{{ $consignment->receiver_id_reference ?: '—' }}</strong>@endif
            </div>
            <div><span class="text-xs font-semibold text-slate-600 block mb-1">Delivery GPS</span>
                @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))<input type="hidden" id="receiver_latitude" name="receiver_latitude" value="{{ old('receiver_latitude') }}"><input type="hidden" id="receiver_longitude" name="receiver_longitude" value="{{ old('receiver_longitude') }}"><button type="button" id="capture-gps" class="rounded-lg border px-3 py-2 text-xs font-bold"><i class="bi bi-geo-alt"></i> Capture current location</button><small id="gps-status" class="ml-2 text-slate-500"></small>@else<strong class="block rounded-xl border bg-slate-50 p-2 text-xs">{{ $consignment->receiver_latitude && $consignment->receiver_longitude ? $consignment->receiver_latitude.', '.$consignment->receiver_longitude : 'Not captured' }}</strong>@endif
            </div>
        </div>
    </section>

    {{-- Section 2: Received Items Table Card --}}
    <section class="serial-card mb-6 shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-lg font-bold text-slate-800 !mb-0">Received Items</h2>
                <p class="text-xs text-slate-400">Record received, missing (short) and damaged quantities.</p>
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
                        <th class="pod-outcome-column py-3 px-3 font-bold text-center bg-blue-50 text-blue-900 border-x border-blue-100">Received Qty</th>
                        <th class="pod-outcome-column py-3 px-3 font-bold text-center">Short Qty</th>
                        <th class="pod-outcome-column py-3 px-3 font-bold text-center">Damaged Qty</th>
                        <th class="pod-outcome-column py-3 px-3 font-bold">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach($consignment->lines as $index => $line)
                        @php($source = $line->transferLine)
                        @php($unit = $source->stockItem->product->unit?->short_name ?? 'Ctn')
                        @php($loaded = $source->quantity)
                        @php($isEditable = $consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))

                        <tr class="hover:bg-slate-50 transition-colors line-row" data-loaded="{{ $loaded }}">
                            <td class="py-3 px-3 font-bold text-slate-800">
                                {{ $source->stockItem->product->name }}
                                @if($source->stockItem->variant->isNotEmpty())
                                    <span class="text-slate-500 font-normal"> / {{ $source->stockItem->variant->first()->value }}</span>
                                @endif
                                @foreach($source->lots as $lot)
                                    <span class="block text-[11px] font-normal text-slate-400">Lot: {{ $lot->lot_number }}</span>
                                @endforeach
                                @foreach($source->serials as $serialIndex => $serial)
                                    @php($savedOutcome = $line->serialOutcomes->firstWhere('serial_id', $serial->id))
                                    <div class="mt-1 flex min-w-[290px] items-center gap-2 rounded-lg bg-slate-50 px-2 py-1 text-[11px] font-normal">
                                        <span class="min-w-0 flex-1 font-mono text-slate-600">{{ $serial->serial_number }}</span>
                                        @if($isEditable)
                                            <input type="hidden" name="lines[{{ $index }}][serial_outcomes][{{ $serialIndex }}][serial_id]" value="{{ $serial->id }}">
                                            <select name="lines[{{ $index }}][serial_outcomes][{{ $serialIndex }}][outcome]" class="serial-outcome rounded-md border-slate-300 py-1 text-[10px]" required>
                                                @foreach(['delivered'=>'Delivered','damaged'=>'Damaged','missing'=>'Short'] as $value=>$label)<option value="{{ $value }}" @selected(old('lines.'.$index.'.serial_outcomes.'.$serialIndex.'.outcome', 'delivered')===$value)>{{ $label }}</option>@endforeach
                                            </select>
                                        @else
                                            <strong class="text-slate-700">{{ ucfirst($savedOutcome?->outcome ?? 'pending') }}</strong>
                                        @endif
                                    </div>
                                @endforeach
                            </td>

                            <td class="py-3 px-3 text-center font-extrabold text-slate-700">
                                {{ $loaded }}
                            </td>

                            {{-- Received Qty (Delivered) --}}
                            <td class="pod-outcome-column py-2 px-3 text-center bg-blue-50/50 border-x border-blue-100">
                                @if($isEditable)
                                    <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
                                    <input type="{{ $source->serials->isNotEmpty() ? 'hidden' : 'number' }}" name="lines[{{ $index }}][delivered]" required min="0" max="{{ $loaded }}" step="{{ $source->stockItem->product->unit?->allow_decimal ? '0.0001' : '1' }}" value="{{ old('lines.'.$index.'.delivered', $line->delivered_quantity ?: $loaded) }}" class="w-20 text-center rounded-lg bg-blue-100 border border-blue-300 font-extrabold text-blue-900 py-1 text-xs focus:ring-2 focus:ring-blue-500 qty-input input-delivered"><span class="serial-total-label {{ $source->serials->isEmpty() ? 'hidden' : '' }}">{{ $loaded }}</span>
                                @else
                                    <strong class="font-extrabold text-blue-800">{{ $line->delivered_quantity }}</strong>
                                @endif
                            </td>

                            {{-- Short Qty (Missing) --}}
                            <td class="pod-outcome-column py-2 px-3 text-center">
                                @if($isEditable)
                                    <input type="{{ $source->serials->isNotEmpty() ? 'hidden' : 'number' }}" name="lines[{{ $index }}][missing]" required min="0" max="{{ $loaded }}" step="{{ $source->stockItem->product->unit?->allow_decimal ? '0.0001' : '1' }}" value="{{ old('lines.'.$index.'.missing', $line->missing_quantity ?? 0) }}" class="w-16 text-center rounded-lg border border-slate-300 font-bold text-slate-800 py-1 text-xs focus:ring-2 focus:ring-rose-500 qty-input input-missing"><span class="serial-total-label {{ $source->serials->isEmpty() ? 'hidden' : '' }}">0</span>
                                @else
                                    <span class="{{ $line->missing_quantity > 0 ? 'text-rose-600 font-extrabold' : 'text-slate-600 font-bold' }}">
                                        {{ $line->missing_quantity }}
                                    </span>
                                @endif
                            </td>

                            {{-- Damaged Qty --}}
                            <td class="pod-outcome-column py-2 px-3 text-center">
                                @if($isEditable)
                                    <input type="{{ $source->serials->isNotEmpty() ? 'hidden' : 'number' }}" name="lines[{{ $index }}][damaged]" required min="0" max="{{ $loaded }}" step="{{ $source->stockItem->product->unit?->allow_decimal ? '0.0001' : '1' }}" value="{{ old('lines.'.$index.'.damaged', $line->damaged_quantity ?? 0) }}" class="w-16 text-center rounded-lg border border-slate-300 font-bold text-slate-800 py-1 text-xs focus:ring-2 focus:ring-rose-500 qty-input input-damaged"><span class="serial-total-label {{ $source->serials->isEmpty() ? 'hidden' : '' }}">0</span>
                                @else
                                    <span class="{{ $line->damaged_quantity > 0 ? 'text-rose-600 font-extrabold bg-rose-50 px-2 py-0.5 rounded-md' : 'text-slate-600 font-bold' }}">
                                        {{ $line->damaged_quantity }}
                                    </span>
                                    @if($line->damageDisposition)<small class="mt-1 block text-[9px] font-bold text-amber-700">Quarantine: {{ $line->damageDisposition->location->name }}</small>@endif
                                @endif
                            </td>

                            {{-- Remarks --}}
                            <td class="pod-outcome-column py-2 px-3">
                                @if($isEditable)
                                    <input type="text" name="lines[{{ $index }}][remarks]" placeholder="Required for damaged/short" maxlength="500" value="{{ old('lines.'.$index.'.remarks', $line->remarks) }}" class="w-full rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-700 input-remarks">
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
        @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))
        <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-slate-100 pt-3">
            <button type="button" id="btn-all-received" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-all">
                <i class="bi bi-check-circle-fill text-sm"></i> Confirm Received
            </button>
            <button type="button" id="btn-record-damage" class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-all">
                <i class="bi bi-exclamation-triangle-fill text-sm text-amber-600"></i> Record Damage
            </button>
            <button type="button" id="btn-capture-photo" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 transition-all">
                <i class="bi bi-camera-fill text-sm text-slate-500"></i> Capture Photo
            </button>
        </div>
        @endif
    </section>

    {{-- Section 3: Signature & Proof of Delivery Photos Cards --}}
    <div class="pod-proof-grid {{ ! $signatureProof && $photoProofs->isEmpty() ? 'pod-empty-proof' : '' }} mb-6 grid gap-5 lg:grid-cols-2">
        {{-- Receiver Signature Card --}}
        <div class="pod-signature-card {{ $signatureProof ? '' : 'pod-empty-proof' }} rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-bold text-slate-800">Receiver Signature</h3>
                    <button type="button" id="clear-sig-btn" class="text-xs font-bold text-blue-600 hover:underline">Clear</button>
                </div>

                @if($signatureProof)
                    <div class="mb-3 rounded-xl border border-slate-200 bg-slate-50 p-2 flex items-center justify-center">
                        <img src="{{ route('delivery.consignments.proofs.show', [$consignment, $signatureProof]) }}" alt="Receiver Signature" class="max-h-32 object-contain">
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
            @if(! $signatureProof)<p class="mt-2 text-[10px] text-slate-400">A signature or at least one photo is required to submit POD.</p>@endif
        </div>

        {{-- Proof of Delivery Photos Card --}}
        <div class="pod-photo-card {{ $photoProofs->isEmpty() ? 'pod-empty-proof' : '' }} rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                <h3 class="text-sm font-bold text-slate-800">Proof of Delivery Photos</h3>
                <span class="text-xs text-slate-400 font-semibold">{{ $photoProofs->count() }} attached</span>
            </div>

            <div class="grid grid-cols-3 gap-3" id="photo-thumbnails-container">
                @foreach($photoProofs as $photoProof)
                    <div><a href="{{ route('delivery.consignments.proofs.show', [$consignment, $photoProof]) }}" target="_blank" class="group relative block aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-100">
                        <img src="{{ route('delivery.consignments.proofs.show', [$consignment, $photoProof]) }}" alt="Proof Photo" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-200">
                    </a><p class="mt-1 truncate text-[10px] font-bold">{{ $photoProof->caption ?: 'Delivery proof' }}</p><p class="text-[9px] text-slate-400">{{ $photoProof->uploader?->name }} · {{ $photoProof->created_at?->format('d M Y H:i') }}</p>@if(auth()->user()->canUseDelivery('delivery.proof.manage'))<button type="submit" form="delete-proof-{{ $photoProof->id }}" class="text-[10px] font-bold text-rose-600">Remove incorrect photo</button>@endif</div>
                @endforeach

                {{-- Add Photo Button Box --}}
                @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))
                <label for="photos-input" class="flex flex-col items-center justify-center aspect-square rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/50 hover:bg-slate-100 hover:border-blue-400 cursor-pointer transition-all">
                    <i class="bi bi-plus-lg text-2xl text-slate-400"></i>
                    <span class="mt-1 text-xs font-bold text-slate-600">Add Photo</span>
                    <input id="photos-input" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" capture="environment" class="hidden">
                </label>
                @endif
            </div>
            @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))<input name="photo_caption" maxlength="250" placeholder="Caption for selected photos (optional)" class="mt-3 w-full rounded-xl border-slate-300 text-xs">@endif
        </div>
    </div>

    {{-- Section 4: Notes --}}
    <div class="pod-notes {{ filled($consignment->proof_notes) ? '' : 'pod-empty-proof' }} mb-6 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
        <label for="proof_notes" class="text-xs font-semibold text-slate-600 mb-1 block">Proof / Discrepancy Notes</label>
        @if($consignment->status === 'arrived' && auth()->user()->canUseDelivery('delivery.pod'))
            <textarea id="proof_notes" name="proof_notes" rows="2" maxlength="2000" placeholder="Add optional delivery notes or discrepancy details..." class="w-full rounded-xl border border-slate-300 p-3 text-xs text-slate-800 focus:ring-2 focus:ring-purple-500">{{ old('proof_notes', $consignment->proof_notes) }}</textarea>
        @else
            <p class="text-xs text-slate-700 font-medium p-2 bg-slate-50 rounded-xl border border-slate-200">{{ $consignment->proof_notes ?: 'No discrepancy notes.' }}</p>
        @endif
    </div>

    {{-- Section 5: Delivery Summary Cards & Submit POD Bar --}}
    <div class="pod-summary {{ $hasOutcome ? '' : 'pod-empty-proof' }} mb-6 grid gap-4 lg:grid-cols-12">
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

        </div>

        {{-- Submit POD Main Action Button --}}
        <div class="no-print lg:col-span-4 flex items-stretch">
            @if($consignment->status === 'arrived' && $consignment->arrived_at && auth()->user()->canUseDelivery('delivery.pod'))
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
@foreach($photoProofs as $photoProof)@if(auth()->user()->canUseDelivery('delivery.proof.manage'))<form id="delete-proof-{{ $photoProof->id }}" method="POST" action="{{ route('delivery.consignments.proofs.destroy',[$consignment,$photoProof]) }}" class="hidden">@csrf @method('DELETE')</form>@endif @endforeach
@if(in_array($consignment->status,['delivered','partial','failed'],true) && auth()->user()->canUseDelivery('delivery.pod'))
<section class="serial-card no-print"><h3 class="font-bold">POD correction</h3><p class="mb-3 text-xs text-slate-500">Request a controlled correction. A different supervisor must approve it; approval records a stock reversal and reopens this receipt.</p>
@if($consignment->corrections->where('status','pending')->isEmpty())<form method="POST" action="{{ route('delivery.consignments.corrections.store',$consignment) }}" class="flex flex-wrap gap-2">@csrf<input name="reason" required minlength="10" maxlength="500" placeholder="Explain why this POD must be corrected" class="min-w-[280px] flex-1 rounded-xl border-slate-300"><button class="serial-btn">Request correction</button></form>@else @php($pendingCorrection=$consignment->corrections->firstWhere('status','pending'))<div class="rounded-xl bg-amber-50 p-3 text-sm"><b>Awaiting supervisor:</b> {{ $pendingCorrection->reason }} @if(auth()->user()->canUseDelivery('delivery.correct'))<form method="POST" action="{{ route('delivery.consignments.corrections.approve',$pendingCorrection) }}" class="mt-2">@csrf<button class="serial-btn">Approve and reopen POD</button></form>@endif</div>@endif</section>
@endif

<section class="delivery-print-signatures print-only" aria-label="Delivery receipt signatures">
    <div class="delivery-signature-box">
        <span class="delivery-signature-line"></span>
        <strong>Receiver Signature</strong>
        <small>Name: {{ $consignment->receiver_name ?: '________________________' }}</small>
        <small>Date: ________________________</small>
    </div>
    <div class="delivery-signature-box">
        <span class="delivery-signature-line"></span>
        <strong>Driver / Delivery Officer</strong>
        <small>Name: {{ $consignment->loadingTransfer->driver?->name ?: '________________________' }}</small>
        <small>Date: ________________________</small>
    </div>
    <div class="delivery-signature-box">
        <span class="delivery-signature-line"></span>
        <strong>Authorized Officer</strong>
        <small>Name: ________________________</small>
        <small>Date: ________________________</small>
    </div>
</section>

{{-- Timeline --}}
<section class="delivery-timeline no-print serial-card shadow-sm border border-slate-200/80 rounded-2xl bg-white p-5">
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
<style>
.print-only{display:none}
@media print{
    @page{size:A4 portrait;margin:10mm}
    .delivery-print-header{display:flex!important;align-items:flex-start;justify-content:space-between;gap:24px;border-top:6px solid #6d28d9;border-bottom:1px solid #cbd5e1;margin-bottom:18px;padding:15px 4px 16px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .delivery-print-brand{display:flex;align-items:center;gap:12px;min-width:0}.delivery-print-brand img{width:58px;height:50px;border:1px solid #e2e8f0;border-radius:9px;object-fit:contain;padding:3px}.delivery-print-brand h1{margin:0;color:#0f172a;font-size:18px;font-weight:800}.delivery-print-brand p{margin:2px 0 0;color:#64748b;font-size:9px;line-height:1.35}
    .delivery-print-document{text-align:right}.delivery-print-document span{display:block;color:#6d28d9;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.delivery-print-document strong{display:block;margin-top:4px;color:#0f172a;font-size:17px}.delivery-print-document p{margin-top:3px;color:#94a3b8;font-size:8px}
    .delivery-print-signatures{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:28px;break-inside:avoid;margin-top:34px;padding:0 4px}.delivery-signature-box{display:flex;flex-direction:column;color:#64748b;font-size:9px}.delivery-signature-line{display:block;height:34px;border-bottom:1px solid #64748b;margin-bottom:6px}.delivery-signature-box strong{color:#334155;font-size:9px;text-align:center}.delivery-signature-box small{margin-top:4px;font-size:8px;white-space:nowrap}
    body>.flex>.flex-1>footer,#async-request-loader{display:none!important}html,body,body>.flex,body>.flex>.flex-1,main,#unloading-pod-form,.overflow-x-auto{height:auto!important;max-height:none!important;overflow:visible!important}body>.flex>.flex-1{margin-left:0!important}.serial-card{break-inside:auto!important}.serial-table tr{break-inside:avoid}.serial-table{overflow:visible!important}
    .pod-empty-proof,.pod-pending .pod-outcome-column{display:none!important}.pod-proof-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}.pod-signature-card,.pod-photo-card{break-inside:avoid;padding:12px!important}.line-row td:first-child span{display:inline!important;margin-right:2px}.line-row td:first-child span::after{content:", "}.line-row td:first-child span:last-child::after{content:""}.serial-table th,.serial-table td{padding:6px 7px!important;font-size:9px!important}.serial-card{margin-bottom:10px!important;padding:12px!important}.serial-card>div:first-child{margin-bottom:8px!important;padding-bottom:7px!important}
}
</style>
<script>
AppPage.ready(() => {
    document.getElementById('capture-gps')?.addEventListener('click',()=>{const status=document.getElementById('gps-status');if(!navigator.geolocation){status.textContent='GPS is unavailable';return;}status.textContent='Locating…';navigator.geolocation.getCurrentPosition(p=>{document.getElementById('receiver_latitude').value=p.coords.latitude.toFixed(7);document.getElementById('receiver_longitude').value=p.coords.longitude.toFixed(7);status.textContent='Location captured';},()=>status.textContent='Could not capture location',{enableHighAccuracy:true,timeout:10000});});
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

        rows.forEach(row => {
            const serialSelectors = [...row.querySelectorAll('.serial-outcome')];
            if (serialSelectors.length) {
                const counts = {delivered: 0, damaged: 0, missing: 0};
                serialSelectors.forEach(select => counts[select.value]++);
                row.querySelector('.input-delivered').value = counts.delivered;
                row.querySelector('.input-damaged').value = counts.damaged;
                row.querySelector('.input-missing').value = counts.missing;
                ['delivered', 'missing', 'damaged'].forEach(type => {
                    const input = row.querySelector('.input-' + type);
                    input?.parentElement.querySelector('.serial-total-label')?.replaceChildren(document.createTextNode(input.value));
                });
            }
            const loaded = parseFloat(row.dataset.loaded || '0') || 0;
            const delInput = row.querySelector('.input-delivered');
            const damInput = row.querySelector('.input-damaged');
            const misInput = row.querySelector('.input-missing');

            const del = parseFloat(delInput?.value || '0') || 0;
            const dam = parseFloat(damInput?.value || '0') || 0;
            const mis = parseFloat(misInput?.value || '0') || 0;

            totalDelivered += del;
            totalDamaged += dam;
            totalMissing += mis;
        });

        document.getElementById('stat-delivered').textContent = totalDelivered;
        document.getElementById('stat-partial').textContent = totalMissing;
        document.getElementById('stat-damaged').textContent = totalDamaged;
    }

    form.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', recalculateTotals);
    });
    form.querySelectorAll('.serial-outcome').forEach(select => select.addEventListener('change', recalculateTotals));
    recalculateTotals();

    // Confirm Received Quick Button
    document.getElementById('btn-all-received')?.addEventListener('click', () => {
        rows.forEach(row => {
            const loaded = row.dataset.loaded || '0';
            const del = row.querySelector('.input-delivered');
            const dam = row.querySelector('.input-damaged');
            const mis = row.querySelector('.input-missing');
            if (del) del.value = loaded;
            if (dam) dam.value = 0;
            if (mis) mis.value = 0;
            row.querySelectorAll('.serial-outcome').forEach(select => select.value = 'delivered');
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
        let discrepancyReasonMissing = false;
        rows.forEach(row => {
            const loaded = parseFloat(row.dataset.loaded || '0') || 0;
            const del = parseFloat(row.querySelector('.input-delivered')?.value || '0') || 0;
            const dam = parseFloat(row.querySelector('.input-damaged')?.value || '0') || 0;
            const mis = parseFloat(row.querySelector('.input-missing')?.value || '0') || 0;
            const sum = del + dam + mis;
            if (Math.abs(sum - loaded) > 0.0001) {
                valid = false;
                row.classList.add('bg-rose-50');
            } else {
                row.classList.remove('bg-rose-50');
            }
            if ((dam > 0 || mis > 0) && !row.querySelector('.input-remarks')?.value.trim()) {
                valid = false;
                discrepancyReasonMissing = true;
                row.classList.add('bg-rose-50');
            }
        });

        const hasSignature = Boolean(document.getElementById('signature_data')?.value);
        const hasPhoto = Boolean(photosInput?.files?.length);
        const proofMissing = !hasSignature && !hasPhoto;

        if (!valid) {
            e.preventDefault();
            alert(discrepancyReasonMissing
                ? 'Add a remark for every damaged or short quantity.'
                : 'For every item row, Delivered + Damaged + Short must equal Loaded quantity.');
        } else if (proofMissing) {
            e.preventDefault();
            alert('Add a receiver signature or at least one proof photo before submitting POD.');
        } else {
            document.getElementById('submit-pod-btn').disabled = true;
        }
    });
});
</script>
@endpush
