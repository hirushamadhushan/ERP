@extends('layouts.app')
@section('title','Vehicle Store')
@section('content')
@include('serials.common')
<section class="serial-card">
<div class="flex flex-wrap justify-between gap-3 mb-5"><div><h1 class="text-xl font-bold">Vehicle Store</h1><p class="text-sm text-slate-500">Load from a warehouse. Unload remaining stock back to a warehouse.</p></div>@if(auth()->user()->canUseDelivery('delivery.transfer'))<div><a class="serial-btn" href="{{ route('delivery.transfers.create',['direction'=>'loading','vehicle_id'=>$vehicle?->id]) }}">↑ Loading</a> <a class="serial-btn serial-secondary" href="{{ route('delivery.transfers.create',['direction'=>'unloading','vehicle_id'=>$vehicle?->id]) }}">↓ Unloading</a></div>@endif</div>
<form method="GET" class="serial-grid"><div><label for="vehicle_id">Vehicle</label><select name="vehicle_id" id="vehicle_id"><option value="">Select a vehicle to see stock</option>@foreach($vehicles as $item)<option value="{{ $item->id }}" @selected($vehicle?->id===$item->id)>{{ $item->number }} — {{ $item->name }}</option>@endforeach</select></div><div><label for="q">Product name / SKU</label><input id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search all vehicle stock"></div><div class="flex items-end"><button class="serial-btn">Show Stock</button></div></form>
@if($vehicle)
<p class="my-4 text-sm">Driver: <strong>{{ $vehicle->drivers->first()?->name ?? 'Unassigned' }}</strong> · Store: {{ $vehicle->stores->first()?->name }}</p>
<p class="mb-3 text-xs text-slate-500">Stock exports include the records visible on this server page.</p>
<div class="overflow-x-auto"><table id="vehicle-stock-table" class="serial-table"><thead><tr><th>Product / SKU</th><th>Variation</th><th>On vehicle</th><th>Stock value</th></tr></thead><tbody>@foreach($balances as $row)<tr><td>{{ $row['product']->name }} / {{ $row['product']->code }}</td><td>{{ $row['variation'] }}</td><td>{{ number_format($row['stock'],4) }} {{ $row['product']->unit?->short_name }}</td><td>Rs {{ number_format($row['purchase_value'],2) }}</td></tr>@endforeach</tbody></table></div><div class="mt-4">{{ $products->links() }}</div>
@endif
</section>
<section class="serial-card"><h2>Transfer history</h2><p class="mb-3 text-xs text-slate-500">Exports include the transfer records visible on this server page.</p><div class="overflow-x-auto"><table id="delivery-history-table" class="serial-table"><thead><tr><th>Transfer</th><th>Date</th><th>Operation</th><th>Vehicle</th><th>Warehouse</th><th>Driver</th><th>Reference</th></tr></thead><tbody>@foreach($transfers as $transfer)<tr><td><a class="text-purple-700 font-bold" href="{{ route('delivery.transfers.show',$transfer) }}">DLV-{{ $transfer->id }}</a></td><td>{{ $transfer->created_at->format('Y-m-d H:i') }}</td><td>{{ ucfirst($transfer->direction) }}</td><td>{{ $transfer->vehicle->number }}</td><td>{{ $transfer->warehouse->name }}</td><td>{{ $transfer->driver?->name ?? '—' }}</td><td>{{ $transfer->transaction->reference ?? '—' }}</td></tr>@endforeach</tbody></table></div><div class="mt-4">{{ $transfers->links() }}</div></section>
@php
$deliveryTables = [['id'=>'delivery-history-table','title'=>'Delivery Transfer History','exportColumns'=>[0,1,2,3,4,5,6],'nonOrderable'=>[],'order'=>[[1,'desc']],'emptyTable'=>'No transfers recorded']];
if($vehicle) array_unshift($deliveryTables, ['id'=>'vehicle-stock-table','title'=>'Vehicle Stock - '.$vehicle->number,'exportColumns'=>[0,1,2,3],'nonOrderable'=>[],'order'=>[[0,'asc']],'emptyTable'=>'No vehicle stock found']);
@endphp
@include('delivery.table-tools', compact('deliveryTables'))
@endsection
