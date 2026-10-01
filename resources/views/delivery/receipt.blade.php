@extends('layouts.app')
@section('title','Delivery Transfer Receipt')
@section('content')
@include('serials.common')
<section class="serial-card"><div class="flex justify-between mb-5"><h1 class="text-xl font-bold">DLV-{{ $transfer->id }} · {{ ucfirst($transfer->direction) }}</h1><button class="serial-btn no-print" onclick="window.print()">Print receipt</button></div>
<div class="serial-grid mb-6"><p>Vehicle: <strong>{{ $transfer->vehicle->number }} — {{ $transfer->vehicle->name }}</strong></p><p>Warehouse: {{ $transfer->warehouse->name }}</p><p>Driver: {{ $transfer->driver?->name ?? 'Unassigned' }}</p><p>Date: {{ $transfer->created_at->format('Y-m-d H:i') }}</p><p>Reference: {{ $transfer->transaction->reference ?? '—' }}</p><p>Recorded by user #{{ $transfer->transaction->created_by ?? '—' }}</p></div>
<div class="overflow-x-auto"><table class="serial-table"><thead><tr><th>Product / SKU</th><th>Variation</th><th>Lot / Serials</th><th>Quantity</th></tr></thead><tbody>@foreach($transfer->lines as $line)<tr><td>{{ $line->stockItem->product->name }} / {{ $line->stockItem->product->code }}</td><td>{{ $line->stockItem->variant->first()?->value ?? 'Default' }}</td><td>{{ $line->lots->pluck('lot_number')->join(', ') }} {{ $line->serials->pluck('serial_number')->join(', ') }}</td><td>{{ $line->quantity }} {{ $line->stockItem->product->unit?->short_name }}</td></tr>@endforeach</tbody></table></div>
<p class="my-5 whitespace-pre-wrap">{{ $transfer->transaction->notes }}</p><a class="serial-btn no-print" href="{{ route('delivery.store',['vehicle_id'=>$transfer->vehicle_id]) }}">Back to vehicle stock</a></section>
@endsection
