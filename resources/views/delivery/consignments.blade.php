@extends('layouts.app')
@section('title', 'Delivery Dashboard')
@section('content')
@include('serials.common')

<div class="space-y-4">

@include('delivery.header', ['activeStep' => 1])

{{-- 1. 5 Metric Stat Cards with Gradient Wave Sparklines --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 sm:gap-4">
    {{-- Card 1: Ready to Create --}}
    <a href="{{ route('delivery.consignments.create') }}" class="group rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm hover:shadow-md transition-all flex items-center justify-between overflow-hidden relative text-decoration-none block">
        <div class="flex items-center gap-3 min-w-0 z-10">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-500 border border-blue-100/60 shadow-2xs">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                    <path d="m3.3 7 8.7 5 8.7-5"/>
                    <path d="M12 22V12"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-600 mb-0.5">Ready to create</div>
                <div class="text-2xl font-black text-blue-600 tracking-tight leading-none">{{ $pendingCount }}</div>
            </div>
        </div>
        <svg class="h-8 w-16 shrink-0 opacity-80 pointer-events-none" viewBox="0 0 64 32" fill="none">
            <defs>
                <linearGradient id="wave-blue" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.0"/>
                </linearGradient>
            </defs>
            <path d="M0 24 C16 24, 16 8, 32 16 C48 24, 48 4, 64 8 L64 32 L0 32 Z" fill="url(#wave-blue)"/>
            <path d="M0 24 C16 24, 16 8, 32 16 C48 24, 48 4, 64 8" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </a>

    {{-- Card 2: Loaded --}}
    <a href="{{ route('delivery.consignments.index', ['status'=>'loaded']) }}#delivery-list" class="group rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm hover:shadow-md transition-all flex items-center justify-between overflow-hidden relative text-decoration-none block">
        <div class="flex items-center gap-3 min-w-0 z-10">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-500 border border-emerald-100/60 shadow-2xs">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>
                    <path d="M15 18H9"/>
                    <path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/>
                    <circle cx="17" cy="18.5" r="2.5"/>
                    <circle cx="7" cy="18.5" r="2.5"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-600 mb-0.5">Loaded</div>
                <div class="text-2xl font-black text-emerald-600 tracking-tight leading-none">{{ $counts['loaded'] ?? 0 }}</div>
            </div>
        </div>
        <svg class="h-8 w-16 shrink-0 opacity-80 pointer-events-none" viewBox="0 0 64 32" fill="none">
            <defs>
                <linearGradient id="wave-green" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                </linearGradient>
            </defs>
            <path d="M0 20 C16 28, 24 12, 40 18 C52 22, 56 6, 64 10 L64 32 L0 32 Z" fill="url(#wave-green)"/>
            <path d="M0 20 C16 28, 24 12, 40 18 C52 22, 56 6, 64 10" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </a>

    {{-- Card 3: In Transit --}}
    <a href="{{ route('delivery.consignments.index', ['status'=>'in_transit']) }}#delivery-list" class="group rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm hover:shadow-md transition-all flex items-center justify-between overflow-hidden relative text-decoration-none block">
        <div class="flex items-center gap-3 min-w-0 z-10">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-purple-50 text-purple-500 border border-purple-100/60 shadow-2xs">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-600 mb-0.5">In Transit</div>
                <div class="text-2xl font-black text-purple-600 tracking-tight leading-none">{{ $counts['in_transit'] ?? 0 }}</div>
            </div>
        </div>
        <svg class="h-8 w-16 shrink-0 opacity-80 pointer-events-none" viewBox="0 0 64 32" fill="none">
            <defs>
                <linearGradient id="wave-purple" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.0"/>
                </linearGradient>
            </defs>
            <path d="M0 22 C12 14, 28 26, 44 14 C54 8, 58 16, 64 12 L64 32 L0 32 Z" fill="url(#wave-purple)"/>
            <path d="M0 22 C12 14, 28 26, 44 14 C54 8, 58 16, 64 12" stroke="#8b5cf6" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </a>

    {{-- Card 4: Completed --}}
    <a href="{{ route('delivery.consignments.index', ['status'=>'delivered']) }}#delivery-list" class="group rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm hover:shadow-md transition-all flex items-center justify-between overflow-hidden relative text-decoration-none block">
        <div class="flex items-center gap-3 min-w-0 z-10">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-500 border border-emerald-100/60 shadow-2xs">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-600 mb-0.5">Completed</div>
                <div class="text-2xl font-black text-emerald-600 tracking-tight leading-none">{{ ($counts['delivered'] ?? 0) + ($counts['partial'] ?? 0) }}</div>
            </div>
        </div>
        <svg class="h-8 w-16 shrink-0 opacity-80 pointer-events-none" viewBox="0 0 64 32" fill="none">
            <defs>
                <linearGradient id="wave-green2" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#10b981" stop-opacity="0.0"/>
                </linearGradient>
            </defs>
            <path d="M0 24 C16 18, 32 26, 48 12 C56 6, 60 14, 64 8 L64 32 L0 32 Z" fill="url(#wave-green2)"/>
            <path d="M0 24 C16 18, 32 26, 48 12 C56 6, 60 14, 64 8" stroke="#10b981" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </a>

    {{-- Card 5: Failed / Cancelled --}}
    <a href="{{ route('delivery.consignments.index', ['status'=>'cancelled']) }}#delivery-list" class="group rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm hover:shadow-md transition-all flex items-center justify-between overflow-hidden relative text-decoration-none block">
        <div class="flex items-center gap-3 min-w-0 z-10">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-500 border border-rose-100/60 shadow-2xs">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-600 mb-0.5">Failed / Cancelled</div>
                <div class="text-2xl font-black text-rose-600 tracking-tight leading-none">{{ ($counts['failed'] ?? 0) + ($counts['cancelled'] ?? 0) }}</div>
            </div>
        </div>
        <svg class="h-8 w-16 shrink-0 opacity-80 pointer-events-none" viewBox="0 0 64 32" fill="none">
            <defs>
                <linearGradient id="wave-rose" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#f43f5e" stop-opacity="0.0"/>
                </linearGradient>
            </defs>
            <path d="M0 22 C16 16, 28 28, 44 16 C54 10, 58 18, 64 14 L64 32 L0 32 Z" fill="url(#wave-rose)"/>
            <path d="M0 22 C16 16, 28 28, 44 16 C54 10, 58 18, 64 14" stroke="#f43f5e" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </a>
</div>

{{-- 2. Compliance / Expiration Warning Alert Banner --}}
<div class="rounded-xl border border-amber-200/80 bg-[#fffbeb] px-4 py-3 flex flex-wrap items-center justify-between gap-3 shadow-2xs" style="background: #fffbeb !important; border-color: #fef3c7 !important;">
    <div class="flex items-center gap-2 text-xs sm:text-sm font-semibold text-amber-900">
        <svg class="h-4 w-4 text-amber-500 shrink-0" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2L1 21h22L12 2zm0 3.99L19.53 19H4.47L12 5.99zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
        </svg>
        <span>1 vehicle or driver document(s) expire within 30 days.</span>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('delivery.vehicles.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition-all text-decoration-none">
            <i class="bi bi-car-front text-slate-500"></i> Vehicles
        </a>
        <a href="{{ route('delivery.drivers.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition-all text-decoration-none">
            <i class="bi bi-person text-slate-500"></i> Drivers
        </a>
    </div>
</div>

{{-- 3. Deliveries Main Card --}}
<section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm space-y-4" id="delivery-list">
    {{-- Card Header: Title, Count badge, + Create Delivery button --}}
    <div class="flex flex-wrap items-center justify-between gap-3 pb-1 border-b border-slate-100/80">
        <div class="flex items-center gap-2.5">
            <svg class="h-5 w-5 text-purple-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>
                <path d="M15 18H9"/>
                <path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/>
                <circle cx="17" cy="18.5" r="2.5"/>
                <circle cx="7" cy="18.5" r="2.5"/>
            </svg>
            <h2 class="text-base font-extrabold text-slate-900 mb-0">Deliveries</h2>
            <span class="rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-bold text-purple-700 border border-purple-200/70">
                {{ $consignments->total() }} records
            </span>
        </div>
        <div class="flex items-center gap-2">
            @if(request()->anyFilled(['q', 'status', 'driver_id', 'vehicle_id', 'date_from', 'date_to']))
                <a href="{{ route('delivery.consignments.index') }}" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition-all text-decoration-none">
                    <i class="bi bi-x-circle"></i> Clear filters
                </a>
            @endif
            @if(auth()->user()->canUseDelivery('delivery.create'))
                <a href="{{ route('delivery.consignments.create') }}" class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold text-white shadow-md shadow-purple-500/20 hover:brightness-105 transition-all text-decoration-none" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                    <i class="bi bi-plus-lg"></i> Create Delivery
                </a>
            @endif
        </div>
    </div>

    {{-- Filters Form (5 columns matching the mockup) --}}
    <form method="GET" action="{{ route('delivery.consignments.index') }}" id="delivery-filters-bar" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        {{-- Search Input --}}
        <div class="relative">
            <svg class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input id="q" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Search by delivery #, customer, reference..." class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-2 text-xs text-slate-700 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500/20" style="height: 38px;">
        </div>

        {{-- Driver --}}
        <div class="flex flex-col">
            <label class="text-[11px] font-semibold text-slate-600 mb-1" for="driver_id">Driver</label>
            <select id="driver_id" name="driver_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 focus:border-purple-500 focus:outline-none" style="height: 38px;" onchange="this.form.submit()">
                <option value="">All drivers</option>
                @foreach($drivers as $driver)
                    <option value="{{ $driver->id }}" @selected(request('driver_id')==$driver->id)>{{ $driver->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Vehicle --}}
        <div class="flex flex-col">
            <label class="text-[11px] font-semibold text-slate-600 mb-1" for="vehicle_id">Vehicle</label>
            <select id="vehicle_id" name="vehicle_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 focus:border-purple-500 focus:outline-none" style="height: 38px;" onchange="this.form.submit()">
                <option value="">All vehicles</option>
                @foreach($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" @selected(request('vehicle_id')==$vehicle->id)>{{ $vehicle->number }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status --}}
        <div class="flex flex-col">
            <label class="text-[11px] font-semibold text-slate-600 mb-1" for="status">Status</label>
            <select id="status" name="status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 focus:border-purple-500 focus:outline-none" style="height: 38px;" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach(['loaded'=>'Loaded','in_transit'=>'In Transit','arrived'=>'At Customer','delivered'=>'Delivered','partial'=>'Partial','failed'=>'Failed','cancelled'=>'Cancelled'] as $key=>$label)
                    <option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Date From --}}
        <div class="flex flex-col">
            <label class="text-[11px] font-semibold text-slate-600 mb-1" for="date_from">Date From</label>
            <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 focus:border-purple-500 focus:outline-none" style="height: 38px;" onchange="this.form.submit()">
        </div>
    </form>

    {{-- Data Table Container --}}
    <div class="overflow-x-auto rounded-xl border border-slate-200/80">
        <table id="delivery-consignments-table" class="serial-table w-full text-left text-xs">
            <thead>
                <tr>
                    <th class="py-2.5 px-3.5 font-bold uppercase text-[11px]">Delivery No.</th>
                    <th class="py-2.5 px-3.5 font-bold uppercase text-[11px]">Customer</th>
                    <th class="py-2.5 px-3.5 font-bold uppercase text-[11px]">Driver</th>
                    <th class="py-2.5 px-3.5 font-bold uppercase text-[11px]">Vehicle</th>
                    <th class="py-2.5 px-3.5 font-bold uppercase text-[11px]">Created</th>
                    <th class="py-2.5 px-3.5 font-bold uppercase text-[11px]">Status</th>
                    <th class="py-2.5 px-3.5 text-right font-bold uppercase text-[11px]">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @php
                    $statusStyles = [
                        'loaded' => 'background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;',
                        'in_transit' => 'background-color: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe;',
                        'arrived' => 'background-color: #fffbeb; color: #d97706; border: 1px solid #fde68a;',
                        'delivered' => 'background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;',
                        'partial' => 'background-color: #f3e8ff; color: #7c3aed; border: 1px solid #e9d5ff;',
                        'failed' => 'background-color: #fff1f2; color: #e11d48; border: 1px solid #fecdd3;',
                        'cancelled' => 'background-color: #fff1f2; color: #e11d48; border: 1px solid #fecdd3;',
                    ];
                @endphp
                @forelse($consignments as $item)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-3 px-3.5 font-bold">
                            <a class="hover:underline inline-flex items-center gap-1.5 text-decoration-none font-bold" style="color: #6366f1;" href="{{ route('delivery.consignments.show',$item) }}">
                                <i class="bi bi-truck text-purple-500"></i>
                                <span>{{ $item->number }}</span>
                            </a>
                        </td>
                        <td class="py-3 px-3.5 font-bold text-slate-800">
                            {{ $item->customer->name }}
                        </td>
                        <td class="py-3 px-3.5 text-slate-700 font-medium">
                            <span class="inline-flex items-center gap-1.5">
                                <i class="bi bi-person text-slate-400"></i>
                                {{ $item->loadingTransfer->driver?->name ?: 'Unassigned' }}
                            </span>
                        </td>
                        <td class="py-3 px-3.5 text-slate-700 font-medium">
                            <span class="inline-flex items-center gap-1.5">
                                <i class="bi bi-car-front text-slate-400"></i>
                                {{ $item->loadingTransfer->vehicle->number }}
                            </span>
                        </td>
                        <td class="py-3 px-3.5 text-slate-600 font-medium text-xs">
                            <span class="inline-flex items-center gap-1.5">
                                <i class="bi bi-calendar3 text-slate-400"></i>
                                {{ $item->created_at->format('Y-m-d H:i') }}
                            </span>
                        </td>
                        <td class="py-3 px-3.5">
                            <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-bold" style="{{ $statusStyles[$item->status] ?? 'background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;' }}">
                                {{ $item->status === 'in_transit' ? 'In Transit' : ($item->status === 'arrived' ? 'At Customer' : ucwords(str_replace('_',' ',$item->status))) }}
                            </span>
                        </td>
                        <td class="py-3 px-3.5 text-right">
                            @php
                                $actionText = match($item->status) {
                                    'loaded' => 'Start Delivery',
                                    'in_transit' => 'Mark Arrived',
                                    'arrived' => 'Record POD',
                                    default => 'View POD'
                                };
                                $actionIcon = match($item->status) {
                                    'loaded' => 'bi-play',
                                    'in_transit' => 'bi-geo-alt',
                                    'arrived' => 'bi-pen',
                                    default => 'bi-eye'
                                };
                            @endphp
                            <a class="inline-flex items-center gap-1.5 rounded-lg border border-purple-200 bg-white px-3 py-1.5 text-xs font-bold text-purple-600 hover:bg-purple-50 shadow-2xs transition-all text-decoration-none" href="{{ route('delivery.consignments.show',$item) }}">
                                <i class="bi {{ $actionIcon }}"></i> {{ $actionText }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-10 text-center text-slate-400">
                            <i class="bi bi-inbox text-3xl mb-2 block text-slate-300"></i>
                            <p class="font-bold text-slate-700 mb-0">No deliveries match these filters.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $consignments->links() }}</div>
</section>

</div>

<style>
/* Exact clean card and light table styling matching user mockup */
#delivery-list {
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
    border-top: 1px solid rgba(226, 232, 240, 0.9) !important;
    border-radius: 16px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
}
table#delivery-consignments-table thead th,
table.dataTable#delivery-consignments-table thead th,
table.serial-table#delivery-consignments-table thead th,
#delivery-list thead th,
#delivery-list .sticky-data-table-header th,
#delivery-list .sticky-data-table-header table thead th {
    background: #f8f9ff !important;
    color: #475569 !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.03em !important;
    padding: 10px 14px !important;
    border-top: 1px solid #e2e8f0 !important;
    border-bottom: 1px solid #e2e8f0 !important;
    border-right: none !important;
    white-space: nowrap !important;
}
table#delivery-consignments-table thead th::after,
table#delivery-consignments-table thead th::before,
table.dataTable#delivery-consignments-table thead th::after,
table.dataTable#delivery-consignments-table thead th::before,
#delivery-list thead th::after,
#delivery-list thead th::before,
#delivery-list .sticky-data-table-header th::after,
#delivery-list .sticky-data-table-header th::before {
    color: #94a3b8 !important;
    opacity: 0.6 !important;
}
#delivery-consignments-table tbody td {
    padding: 12px 14px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    font-size: 12.5px !important;
    vertical-align: middle !important;
}
#delivery-consignments-table tbody tr:hover {
    background-color: #fafaff !important;
}
</style>

@php($deliveryTables = [['id'=>'delivery-consignments-table','title'=>'Delivery Dashboard','exportColumns'=>[0,1,2,3,4,5],'nonOrderable'=>[6],'order'=>[[4,'desc']],'emptyTable'=>'No deliveries found','searching'=>false]])
@include('delivery.table-tools', compact('deliveryTables'))
@endsection
