@extends('layouts.app')
@section('title',$order->number)
@section('content')
@include('serials.common')
@php
    $statusLabels = ['draft'=>'Draft','confirmed'=>'Confirmed','in_progress'=>'In Production','completed'=>'Completed','cancelled'=>'Cancelled'];
    $printLogo = filled($businessSetting->logo_path ?? null) ? $businessSetting->logo_path : asset('images/codeza-logo.png');
    $printStatus = $statusLabels[$order->status] ?? ucwords(str_replace('_', ' ', $order->status));
@endphp
<div class="mfg-screen no-print">
<div class="mb-5 flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase text-purple-600">Manufacturing order</p><h1 class="text-2xl font-black">{{ $order->number }}</h1><span class="mt-2 inline-block rounded-full bg-purple-100 px-3 py-1 text-xs font-bold text-purple-700">{{ $statusLabels[$order->status]??ucwords(str_replace('_',' ',$order->status)) }}</span></div><button type="button" onclick="window.print()" class="serial-btn inline-flex items-center gap-2 shadow-md shadow-purple-500/20"><i class="bi bi-printer-fill"></i><span>Print Receipt</span></button></div>
@if($order->status==='cancelled')<section class="serial-card border-rose-200 bg-rose-50"><h2 class="text-rose-800">Cancellation details</h2><p>{{ $order->cancellation_reason }}</p><p class="mt-2 text-sm text-slate-600">Cancelled {{ $order->cancelled_at?->format('Y-m-d H:i') }} by {{ $order->canceller?->name }}</p></section>@endif
<section class="serial-card"><div class="grid gap-4 md:grid-cols-4"><div><small>Finished product</small><b class="block">{{ $order->product->name }}</b></div><div><small>Recipe</small><b class="block">{{ $order->billOfMaterial?->code ?: 'Legacy/manual' }}</b></div><div><small>Planned quantity</small><b class="block">{{ $order->quantity }} {{ $order->product->unit?->short_name }}</b></div><div><small>Warehouse</small><b class="block">{{ $order->location->name }}</b></div><div><small>Finished lot</small><b class="block text-purple-700">{{ $order->outputLot?->lot_number ?: 'Created on completion' }}</b></div><div><small>Manufacturing date</small><b class="block">{{ $order->manufactured_at->format('Y-m-d') }}</b></div><div><small>Expiry</small><b class="block">{{ $order->expires_at?->format('Y-m-d') ?: 'No expiry' }}</b></div><div><small>{{ $order->status==='completed'?'Actual':'Estimated' }} unit cost</small><b class="block">Rs {{ number_format($order->unit_cost,2) }}</b></div></div></section>
<div class="grid gap-5 lg:grid-cols-2"><section class="serial-card"><h2>{{ $order->status==='completed'?'Consumed':'Required' }} materials</h2><table class="serial-table"><thead><tr><th>Material / lot</th><th>Qty</th><th>Cost</th></tr></thead><tbody>@foreach($order->components as $line)<tr><td><b>{{ $line->product->name }}{{ $line->productVariant?' · '.$line->productVariant->value:'' }}</b><small class="block">{{ $line->lot?->lot_number ?: ($line->source_lot_reference ?: 'Standard stock') }}</small></td><td>{{ $line->quantity }} {{ $line->product->unit?->short_name }}</td><td>Rs {{ number_format($line->total_cost,2) }}</td></tr>@endforeach</tbody></table></section><section class="serial-card"><h2>Production costs</h2>@forelse($order->expenses as $expense)<div class="flex justify-between border-b py-2"><span>{{ $expense->name }}</span><b>Rs {{ number_format($expense->amount,2) }}</b></div>@empty<p class="text-sm text-slate-500">No additional expenses.</p>@endforelse<div class="mt-4 space-y-2 rounded-xl bg-slate-900 p-4 text-white"><div class="flex justify-between"><span>Materials</span><b>Rs {{ number_format($order->material_cost,2) }}</b></div><div class="flex justify-between"><span>Expenses</span><b>Rs {{ number_format($order->expense_cost,2) }}</b></div><div class="flex justify-between border-t border-white/20 pt-2 text-lg"><span>Total</span><b>Rs {{ number_format($order->total_cost,2) }}</b></div></div></section></div>
@if($order->product->enable_serial && $order->outputSerials->isNotEmpty())<section class="serial-card"><div class="mb-3 flex justify-between"><h2>Manufactured serial numbers</h2><span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">{{ $order->outputSerials->count() }} received</span></div><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">@foreach($order->outputSerials as $serial)<div class="rounded-lg border bg-slate-50 px-3 py-2 font-mono text-sm">{{ $serial->serial_number }}</div>@endforeach</div></section>@endif
<section class="serial-card"><h2>Production timeline</h2><div class="grid gap-3 md:grid-cols-4"><div><small>Drafted</small><b class="block">{{ $order->created_at->format('Y-m-d H:i') }}</b><span>{{ $order->creator?->name }}</span></div><div><small>Confirmed</small><b class="block">{{ $order->confirmed_at?->format('Y-m-d H:i')?:'Pending' }}</b><span>{{ $order->confirmer?->name }}</span></div><div><small>Started</small><b class="block">{{ $order->started_at?->format('Y-m-d H:i')?:'Pending' }}</b><span>{{ $order->starter?->name }}</span></div><div><small>Completed</small><b class="block">{{ $order->completed_at?->format('Y-m-d H:i')?:'Pending' }}</b><span>{{ $order->completer?->name }}</span></div></div></section>
<div class="mt-6 flex justify-end"><a href="{{ route('manufacturing.index') }}" class="serial-btn inline-flex items-center gap-2 shadow-md shadow-purple-500/20"><i class="bi bi-list-ul"></i><span>Back to Manufacturing Orders</span><i class="bi bi-arrow-right"></i></a></div>
</div>

<article class="mfg-print" aria-label="Manufacturing receipt">
    <header class="mfg-print-header">
        <div class="mfg-print-brand">
            <div class="mfg-print-logo"><img src="{{ $printLogo }}" alt=""></div>
            <div><h1>{{ $businessSetting->business_name ?? 'Codeza ERP' }}</h1><p>Manufacturing &amp; Inventory Control</p></div>
        </div>
        <div class="mfg-print-document"><span>MANUFACTURING RECEIPT</span><strong>{{ $order->number }}</strong><small>Issued {{ ($order->completed_at ?? $order->created_at)->format('d M Y, h:i A') }}</small></div>
    </header>

    <div class="mfg-print-rule"></div>
    <section class="mfg-print-summary">
        <div class="mfg-print-output"><small>FINISHED PRODUCT</small><h2>{{ $order->product->name }}</h2><p>{{ $order->quantity }} {{ $order->product->unit?->short_name }} manufactured at {{ $order->location->name }}</p></div>
        <div class="mfg-print-status"><span>{{ $printStatus }}</span><small>Stock posting status</small></div>
    </section>

    <section class="mfg-print-meta">
        <div><small>Output lot</small><strong>{{ $order->outputLot?->lot_number ?: 'Pending completion' }}</strong></div>
        <div><small>Manufacturing date</small><strong>{{ $order->manufactured_at->format('d M Y') }}</strong></div>
        <div><small>Recipe / method</small><strong>{{ $order->billOfMaterial?->code ?: 'Manual production' }}</strong></div>
        <div><small>Unit cost</small><strong>Rs {{ number_format($order->unit_cost,2) }}</strong></div>
    </section>

    <section class="mfg-print-section">
        <div class="mfg-print-section-title"><div><span>01</span><h3>{{ $order->status==='completed'?'Consumed materials':'Required materials' }}</h3></div><small>{{ $order->components->count() }} material line(s)</small></div>
        <table class="mfg-print-table">
            <thead><tr><th>#</th><th>Raw material</th><th>Source lot</th><th class="num">Quantity</th><th class="num">Unit cost</th><th class="num">Amount</th></tr></thead>
            <tbody>@foreach($order->components as $line)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $line->product->name }}</strong>@if($line->productVariant)<small>{{ $line->productVariant->value }}</small>@endif</td><td>{{ $line->lot?->lot_number ?: ($line->source_lot_reference ?: 'Standard stock') }}</td><td class="num">{{ number_format($line->quantity,4) }} {{ $line->product->unit?->short_name }}</td><td class="num">Rs {{ number_format($line->unit_cost,2) }}</td><td class="num"><strong>Rs {{ number_format($line->total_cost,2) }}</strong></td></tr>@endforeach</tbody>
        </table>
    </section>

    <div class="mfg-print-bottom">
        <section class="mfg-print-section mfg-print-expenses">
            <div class="mfg-print-section-title"><div><span>02</span><h3>Additional expenses</h3></div></div>
            @forelse($order->expenses as $expense)<div class="mfg-print-expense"><span>{{ $expense->name }}</span><strong>Rs {{ number_format($expense->amount,2) }}</strong></div>@empty<div class="mfg-print-empty">No additional production expenses recorded.</div>@endforelse
            @if($order->notes)<div class="mfg-print-notes"><small>Production notes</small><p>{{ $order->notes }}</p></div>@endif
        </section>
        <aside class="mfg-print-totals">
            <div><span>Material cost</span><strong>Rs {{ number_format($order->material_cost,2) }}</strong></div>
            <div><span>Additional expenses</span><strong>Rs {{ number_format($order->expense_cost,2) }}</strong></div>
            <div class="grand"><span>TOTAL PRODUCTION COST</span><strong>Rs {{ number_format($order->total_cost,2) }}</strong></div>
            <p>{{ number_format($order->quantity,4) }} {{ $order->product->unit?->short_name }} @ Rs {{ number_format($order->unit_cost,2) }} per unit</p>
        </aside>
    </div>

    <section class="mfg-print-audit">
        <div><small>Prepared by</small><strong>{{ $order->creator?->name ?: 'System user' }}</strong><span>{{ $order->created_at->format('d M Y, h:i A') }}</span></div>
        <div><small>Approved / completed by</small><strong>{{ $order->completer?->name ?: 'Pending approval' }}</strong><span>{{ $order->completed_at?->format('d M Y, h:i A') ?: '—' }}</span></div>
        <div class="mfg-print-sign"><span>Authorized signature</span></div>
    </section>
    <footer class="mfg-print-footer"><span>System-generated manufacturing receipt</span><strong>{{ $businessSetting->business_name ?? 'Codeza ERP' }}</strong><span>Document {{ $order->number }}</span></footer>
</article>

<style>
.mfg-print{display:none}
@media print{
    @page{size:A4 portrait;margin:10mm}
    html,body{height:auto!important;background:#fff!important}
    body{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color:#172033!important}
    aside,body>header,nav,body footer,#delivery-success-alert,[role="alert"],.no-print{display:none!important}
    main{display:block!important;width:100%!important;max-width:none!important;margin:0!important;padding:0!important;overflow:visible!important}
    .mfg-print{display:block!important;width:100%;font-family:"Plus Jakarta Sans",Arial,sans-serif;font-size:9.5pt;line-height:1.4;color:#172033}
    .mfg-print *{box-sizing:border-box}
    .mfg-print-header{display:flex!important;align-items:center;justify-content:space-between;padding:0 0 7mm}
    .mfg-print-brand{display:flex;align-items:center;gap:4mm}.mfg-print-logo{display:grid;width:14mm;height:14mm;place-items:center;overflow:hidden;border-radius:4mm;background:#111827}.mfg-print-logo img{width:100%;height:100%;object-fit:contain}.mfg-print-brand h1{margin:0;font-size:17pt;line-height:1.15;font-weight:800;color:#111827}.mfg-print-brand p{margin:1mm 0 0;color:#64748b;font-size:8.5pt}
    .mfg-print-document{text-align:right}.mfg-print-document span{display:block;color:#7c3aed;font-size:7.5pt;font-weight:800;letter-spacing:.14em}.mfg-print-document strong{display:block;margin:1mm 0;font-size:14pt;color:#111827}.mfg-print-document small{color:#64748b}
    .mfg-print-rule{height:1.6mm;border-radius:99px;background:linear-gradient(90deg,#111827 0 35%,#7c3aed 35% 82%,#10b981 82%)}
    .mfg-print-summary{display:flex;align-items:center;justify-content:space-between;margin:6mm 0;padding:5mm 6mm;border:1px solid #ddd6fe;border-radius:4mm;background:#faf7ff}.mfg-print-output small{color:#7c3aed;font-size:7.5pt;font-weight:800;letter-spacing:.12em}.mfg-print-output h2{margin:1mm 0;font-size:16pt;color:#111827}.mfg-print-output p{margin:0;color:#64748b}.mfg-print-status{text-align:right}.mfg-print-status span{display:inline-block;padding:2mm 4mm;border-radius:99px;background:#dcfce7;color:#047857;font-weight:800}.mfg-print-status small{display:block;margin-top:1.5mm;color:#64748b}
    .mfg-print-meta{display:grid;grid-template-columns:repeat(4,1fr);gap:3mm;margin-bottom:6mm}.mfg-print-meta div{padding:3.5mm;border:1px solid #e2e8f0;border-radius:3mm}.mfg-print-meta small{display:block;margin-bottom:1mm;color:#64748b;font-size:7.5pt}.mfg-print-meta strong{display:block;font-size:9pt;color:#172033;overflow-wrap:anywhere}
    .mfg-print-section{break-inside:avoid}.mfg-print-section-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:2.5mm}.mfg-print-section-title>div{display:flex;align-items:center;gap:2mm}.mfg-print-section-title span{display:grid;width:6mm;height:6mm;place-items:center;border-radius:2mm;background:#ede9fe;color:#6d28d9;font-size:7pt;font-weight:800}.mfg-print-section-title h3{margin:0;font-size:11pt}.mfg-print-section-title>small{color:#64748b}
    .mfg-print-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid #dbe2ea;border-radius:3mm;overflow:hidden}.mfg-print-table th{padding:2.6mm 3mm;background:#172033;color:#fff;font-size:7.5pt;text-align:left;text-transform:uppercase;letter-spacing:.04em}.mfg-print-table td{padding:3mm;border-bottom:1px solid #e5e7eb;vertical-align:top}.mfg-print-table tbody tr:last-child td{border-bottom:0}.mfg-print-table td:first-child{color:#94a3b8}.mfg-print-table td strong{color:#111827}.mfg-print-table td small{display:block;color:#64748b}.mfg-print-table .num{text-align:right;white-space:nowrap}
    .mfg-print-bottom{display:grid;grid-template-columns:1fr 74mm;gap:7mm;margin-top:7mm;align-items:start}.mfg-print-expense{display:flex;justify-content:space-between;padding:2mm 0;border-bottom:1px solid #e5e7eb}.mfg-print-empty{padding:4mm;border:1px dashed #cbd5e1;border-radius:3mm;color:#64748b}.mfg-print-notes{margin-top:4mm;padding:3mm;border-left:1.2mm solid #7c3aed;background:#faf7ff}.mfg-print-notes small{color:#7c3aed;font-weight:700}.mfg-print-notes p{margin:1mm 0 0}
    .mfg-print-totals{display:block!important;padding:5mm;border-radius:4mm;background:#172033!important;color:#fff!important}.mfg-print-totals div{display:flex;justify-content:space-between;gap:4mm;padding:2mm 0;color:#cbd5e1}.mfg-print-totals div strong{color:#fff;white-space:nowrap}.mfg-print-totals .grand{display:block;margin-top:2mm;padding-top:4mm;border-top:1px solid #475569}.mfg-print-totals .grand span{display:block;font-size:7pt;letter-spacing:.08em}.mfg-print-totals .grand strong{display:block;margin-top:1mm;font-size:15pt;color:#a7f3d0}.mfg-print-totals p{margin:3mm 0 0;color:#94a3b8;font-size:7.5pt}
    .mfg-print-audit{display:grid;grid-template-columns:1fr 1fr 1fr;gap:7mm;margin-top:10mm;padding-top:5mm;border-top:1px solid #cbd5e1;break-inside:avoid}.mfg-print-audit small,.mfg-print-audit strong,.mfg-print-audit span{display:block}.mfg-print-audit small{color:#64748b}.mfg-print-audit strong{margin-top:1mm}.mfg-print-audit span{color:#64748b;font-size:7.5pt}.mfg-print-sign{display:flex;align-items:flex-end;border-bottom:1px solid #64748b}.mfg-print-sign span{padding-bottom:1mm}
    .mfg-print-footer{display:flex!important;justify-content:space-between!important;margin-top:8mm;padding-top:3mm;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:7pt}.mfg-print-footer strong{color:#7c3aed}
}
</style>
@endsection
