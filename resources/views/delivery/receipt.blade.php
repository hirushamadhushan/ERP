@extends('layouts.app')
@section('title', 'Delivery Transfer Receipt')
@section('content')
@php
    $loading = $transfer->direction === 'loading';
    $source = $loading ? $transfer->warehouse->name : $transfer->vehicle->number.' — '.$transfer->vehicle->name;
    $destination = $loading ? $transfer->vehicle->number.' — '.$transfer->vehicle->name : $transfer->warehouse->name;
    $totalQuantity = $transfer->lines->sum(fn ($line) => (float) $line->quantity);
    $documentNumber = 'DLV-'.str_pad((string) $transfer->id, 6, '0', STR_PAD_LEFT);
    $logo = $businessSetting->logo_path ?? asset('images/codeza-logo.png');
@endphp

<style>
.receipt-shell{max-width:1000px;margin:0 auto}.receipt-paper{overflow:hidden;border:1px solid #e2e8f0;border-radius:20px;background:#fff;box-shadow:0 12px 35px rgb(15 23 42 / .08)}
.receipt-accent{height:7px;background:linear-gradient(90deg,#7c3aed,#4f46e5,#0d9488)}.receipt-inner{padding:30px}.receipt-meta-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.receipt-meta{border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:12px}.receipt-meta span{display:block;margin-bottom:4px;color:#64748b;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.receipt-meta strong{display:block;color:#0f172a;font-size:12px;overflow-wrap:anywhere}
.receipt-route{display:grid;grid-template-columns:1fr 62px 1fr;align-items:center;gap:15px;margin:22px 0;padding:18px;border:1px solid #ddd6fe;border-radius:16px;background:linear-gradient(135deg,#faf5ff,#f8fafc)}.receipt-place small{display:block;margin-bottom:5px;color:#7c3aed;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.receipt-place strong{color:#1e293b;font-size:14px}.receipt-arrow{display:flex;height:42px;width:42px;align-items:center;justify-content:center;justify-self:center;border-radius:50%;background:#7c3aed;color:#fff;font-size:18px;box-shadow:0 5px 14px rgb(124 58 237 / .25)}
.receipt-table{width:100%;border-collapse:collapse}.receipt-table th{border-bottom:2px solid #cbd5e1;background:#f8fafc;padding:11px 10px;color:#475569;font-size:10px;font-weight:900;letter-spacing:.04em;text-align:left;text-transform:uppercase}.receipt-table td{border-bottom:1px solid #e2e8f0;padding:13px 10px;color:#334155;font-size:11px;vertical-align:top}.receipt-table tr{break-inside:avoid}.serial-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;margin-top:8px}.serial-pill{border:1px solid #ddd6fe;border-radius:6px;background:#faf5ff;padding:5px 7px;color:#6d28d9;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:9px;overflow-wrap:anywhere}
.receipt-summary{display:flex;justify-content:flex-end;margin-top:18px}.receipt-total{min-width:250px;border-radius:14px;background:#0f172a;padding:15px 18px;color:#fff}.receipt-total div{display:flex;align-items:center;justify-content:space-between;gap:20px}.receipt-total span{color:#cbd5e1;font-size:11px}.receipt-total strong{font-size:19px}.receipt-notes{margin-top:18px;border-left:4px solid #8b5cf6;border-radius:0 10px 10px 0;background:#faf5ff;padding:12px 14px}.receipt-notes strong{display:block;margin-bottom:4px;color:#6d28d9;font-size:10px;text-transform:uppercase}.receipt-notes p{color:#475569;font-size:11px;white-space:pre-wrap}.receipt-signatures{display:grid;grid-template-columns:repeat(3,1fr);gap:34px;margin-top:54px}.receipt-signature{border-top:1px solid #94a3b8;padding-top:7px;text-align:center;color:#64748b;font-size:10px}.receipt-footer{display:flex;justify-content:space-between;gap:20px;border-top:1px solid #e2e8f0;margin-top:28px;padding-top:12px;color:#94a3b8;font-size:9px}
@media(max-width:720px){.receipt-inner{padding:18px}.receipt-meta-grid{grid-template-columns:repeat(2,1fr)}.receipt-route{grid-template-columns:1fr}.receipt-arrow{transform:rotate(90deg)}.serial-list{grid-template-columns:1fr}.receipt-signatures{gap:16px}.receipt-footer{flex-direction:column}}
@media print{
    @page{size:A4 portrait;margin:10mm}
    #main-sidebar,#sidebar-backdrop,#async-request-loader,.no-print,body>.flex>.flex-1>header,body>.flex>.flex-1>footer{display:none!important}
    html,body,body>.flex,body>.flex>.flex-1{height:auto!important;min-height:0!important;background:#fff!important}
    body>.flex>.flex-1{margin-left:0!important}
    body>.flex>.flex-1>main{margin:0!important;padding:0!important;overflow:visible!important}
    .receipt-shell{max-width:none;margin:0}.receipt-paper{border:0;border-radius:0;box-shadow:none}.receipt-inner{padding:10px 4px}.receipt-accent{height:5px}.receipt-meta,.receipt-route,.serial-pill,.receipt-total,.receipt-notes,.receipt-table th,.receipt-accent{-webkit-print-color-adjust:exact;print-color-adjust:exact}.receipt-route{margin:16px 0}.receipt-signatures{margin-top:44px}.receipt-footer{margin-top:20px}
}
</style>

<div class="receipt-shell">
    <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-xl font-extrabold text-slate-900">Transfer receipt</h1><p class="text-xs text-slate-500">Review the stock movement details or print an official copy.</p></div>
        <div class="flex gap-2"><a href="{{ route('delivery.store', ['vehicle_id' => $transfer->vehicle_id]) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Back to vehicle stock</a><button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2.5 text-xs font-bold text-white shadow-md hover:bg-purple-700"><i class="bi bi-printer"></i> Print receipt</button></div>
    </div>

    <article class="receipt-paper">
        <div class="receipt-accent"></div>
        <div class="receipt-inner">
            <header class="flex items-start justify-between gap-6 border-b border-slate-200 pb-5">
                <div class="flex min-w-0 items-center gap-4"><img src="{{ $logo }}" alt="{{ $businessSetting->business_name ?? 'Codeza ERP' }} logo" class="h-14 w-16 rounded-xl border border-slate-100 object-contain p-1"><div><p class="text-lg font-extrabold text-slate-900">{{ $businessSetting->business_name ?? 'Codeza ERP' }}</p><p class="text-[10px] font-bold uppercase tracking-[.18em] text-purple-600">Inventory Transfer Receipt</p></div></div>
                <div class="text-right"><span class="inline-flex rounded-full px-3 py-1 text-[10px] font-extrabold uppercase {{ $loading ? 'bg-emerald-100 text-emerald-700' : 'bg-violet-100 text-violet-700' }}">{{ $loading ? 'Vehicle Loading' : 'Vehicle Unloading' }}</span><p class="mt-2 text-xl font-black text-slate-900">{{ $documentNumber }}</p><p class="text-[10px] text-slate-500">Generated {{ now()->format('Y-m-d H:i') }}</p></div>
            </header>

            <div class="receipt-route"><div class="receipt-place"><small>Stock source</small><strong>{{ $source }}</strong></div><div class="receipt-arrow"><i class="bi bi-arrow-right"></i></div><div class="receipt-place"><small>Stock destination</small><strong>{{ $destination }}</strong></div></div>

            <div class="receipt-meta-grid">
                <div class="receipt-meta"><span>Transaction date</span><strong>{{ ($transfer->transaction->occurred_at ?? $transfer->created_at)->format('Y-m-d H:i') }}</strong></div>
                <div class="receipt-meta"><span>Reference</span><strong>{{ $transfer->transaction->reference ?: 'Not provided' }}</strong></div>
                <div class="receipt-meta"><span>Driver</span><strong>{{ $transfer->driver?->name ?? 'Unassigned' }}</strong></div>
                <div class="receipt-meta"><span>Recorded by</span><strong>{{ $transfer->transaction->creator?->name ?? 'User #'.($transfer->transaction->created_by ?? '—') }}</strong></div>
            </div>

            <div class="mt-6 overflow-hidden rounded-xl border border-slate-200">
                <table class="receipt-table">
                    <thead><tr><th style="width:42%">Product</th><th style="width:18%">Variation</th><th style="width:22%">Tracking</th><th style="width:18%;text-align:right">Quantity</th></tr></thead>
                    <tbody>
                    @foreach($transfer->lines as $line)
                        @php($serials = $line->serials->pluck('serial_number'))
                        <tr>
                            <td><strong class="block text-xs text-slate-900">{{ $line->stockItem->product->name }}</strong><span class="mt-1 block font-mono text-[9px] text-slate-500">{{ $line->stockItem->product->code }}</span></td>
                            <td>{{ $line->stockItem->variant->first()?->value ?? 'Default' }}</td>
                            <td>@if($line->lots->isNotEmpty())<strong>Lot:</strong> {{ $line->lots->pluck('lot_number')->join(', ') }}@elseif($serials->isNotEmpty())<span class="font-bold text-violet-700">{{ $serials->count() }} serial number(s)</span>@else<span class="text-slate-400">Standard stock</span>@endif</td>
                            <td style="text-align:right"><strong class="text-sm text-slate-900">{{ number_format((float) $line->quantity, $line->stockItem->product->unit?->allow_decimal ? 4 : 0) }}</strong> {{ $line->stockItem->product->unit?->short_name }}</td>
                        </tr>
                        @if($serials->isNotEmpty())<tr><td colspan="4" class="!pt-0"><div class="serial-list">@foreach($serials as $serial)<span class="serial-pill">{{ $serial }}</span>@endforeach</div></td></tr>@endif
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="receipt-summary"><div class="receipt-total"><div><span>Total transfer quantity</span><strong>{{ number_format($totalQuantity, 4) }}</strong></div><div class="mt-1"><span>Product lines</span><b>{{ $transfer->lines->count() }}</b></div></div></div>
            @if(filled($transfer->transaction->notes))<div class="receipt-notes"><strong>Notes / Comments</strong><p>{{ $transfer->transaction->notes }}</p></div>@endif
            <div class="receipt-signatures"><div class="receipt-signature">Prepared by</div><div class="receipt-signature">Driver / Transport officer</div><div class="receipt-signature">Warehouse officer</div></div>
            <footer class="receipt-footer"><span>{{ $businessSetting->business_name ?? 'Codeza ERP' }} · Delivery & Inventory Management</span><span>{{ $documentNumber }} · Page 1</span></footer>
        </div>
    </article>

    @if($loading && (! $transfer->consignment || $transfer->consignment->status === 'cancelled') && $transfer->hasStockAvailableForConsignment() && auth()->user()->canUseDelivery('delivery.create'))<div class="no-print mt-4 flex justify-end"><a class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-emerald-700" href="{{ route('delivery.consignments.create', ['loading_transfer_id' => $transfer->id]) }}">Create customer delivery</a></div>@endif
</div>
@endsection
