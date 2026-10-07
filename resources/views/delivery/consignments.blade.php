@extends('layouts.app')
@section('title', 'Delivery Dashboard')
@section('content')
@include('serials.common')
@include('delivery.header', ['activeStep' => 1])

<div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
    @foreach([
        ['Ready to create', $pendingCount, 'bi-box-seam', 'text-blue-700', '#next-actions'],
        ['Loaded', $counts['loaded'] ?? 0, 'bi-box-arrow-in-right', 'text-emerald-700', route('delivery.consignments.index', ['status'=>'loaded']).'#delivery-list'],
        ['In transit', $counts['in_transit'] ?? 0, 'bi-truck', 'text-indigo-700', route('delivery.consignments.index', ['status'=>'in_transit']).'#delivery-list'],
        ['At customer', $counts['arrived'] ?? 0, 'bi-geo-alt', 'text-fuchsia-700', route('delivery.consignments.index', ['status'=>'arrived']).'#delivery-list'],
        ['Completed', ($counts['delivered'] ?? 0) + ($counts['partial'] ?? 0), 'bi-check-circle', 'text-violet-700', '#delivery-list'],
        ['Failed', $counts['failed'] ?? 0, 'bi-exclamation-triangle', 'text-rose-700', route('delivery.consignments.index', ['status'=>'failed']).'#delivery-list']
    ] as [$label,$count,$icon,$color,$url])
        <a href="{{ $url }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md" title="View {{ strtolower($label) }} records">
            <i class="bi {{ $icon }} {{ $color }}"></i><p class="mt-2 text-xs text-slate-500">{{ $label }}</p><strong class="text-2xl {{ $color }}">{{ number_format($count) }}</strong>
        </a>
    @endforeach
</div>

@if($activeDeliveries->isNotEmpty())
<section class="serial-card border-violet-200 bg-violet-50/40">
    <div class="mb-3"><h2 class="!mb-0">Deliveries requiring action</h2><p class="text-sm text-slate-500">Open the current delivery step directly from here.</p></div>
    <div class="grid gap-3 lg:grid-cols-2">
        @foreach($activeDeliveries as $delivery)
            @php($action = match($delivery->status) {'loaded'=>'Start delivery','in_transit'=>'Mark arrived','arrived'=>'Record receipt & POD',default=>'Open delivery'})
            <a href="{{ route('delivery.consignments.show', $delivery) }}" class="group flex w-full flex-wrap items-center justify-between gap-3 rounded-xl border border-violet-200 bg-white p-3 shadow-sm transition hover:border-violet-500 hover:bg-violet-50 hover:shadow-md" aria-label="{{ $action }} for {{ $delivery->number }}">
                <div class="min-w-0"><div class="flex items-center gap-2"><strong class="text-sm text-slate-900">{{ $delivery->number }}</strong><span class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-700">{{ ucwords(str_replace('_',' ',$delivery->status)) }}</span></div><p class="mt-1 truncate text-xs text-slate-500">{{ $delivery->customer->name }} · {{ $delivery->loadingTransfer->vehicle->number }} · {{ $delivery->loadingTransfer->driver?->name ?: 'Unassigned driver' }}</p></div>
                <span class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white group-hover:bg-violet-700">{{ $action }} <i class="bi bi-arrow-right"></i></span>
            </a>
        @endforeach
    </div>
</section>
@endif

@if($fleetAlerts)
    <div class="mb-5 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <span><i class="bi bi-exclamation-triangle mr-2"></i><strong>{{ $fleetAlerts }}</strong> active vehicle or driver document(s) are expired or expire within 30 days.</span>
        <div class="flex gap-3"><a class="font-bold underline" href="{{ route('delivery.vehicles.index') }}">Vehicles</a><a class="font-bold underline" href="{{ route('delivery.drivers.index') }}">Drivers</a></div>
    </div>
@endif

<section class="serial-card" id="next-actions">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="!mb-0">Next actions</h2><p class="text-sm text-slate-500">Turn completed vehicle loading records into customer deliveries.</p></div>
        @if(auth()->user()->canUseDelivery('delivery.transfer'))<a class="serial-btn" href="{{ route('delivery.consignments.create') }}">Create customer delivery</a>@endif
    </div>
    @if($pendingTransfers->isNotEmpty())
        <div class="mt-4 divide-y divide-violet-100 rounded-xl border border-violet-200 bg-violet-50 px-4">
            @foreach($pendingTransfers as $transfer)
                <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                    <span><strong>{{ $transfer->transaction?->reference ?: 'Loading #'.$transfer->id }}</strong> · {{ $transfer->vehicle?->number }} · {{ $transfer->warehouse?->name }} · {{ $transfer->lines_count }} line(s)</span>
                    @if(auth()->user()->canUseDelivery('delivery.transfer'))<a class="font-bold text-violet-700 underline" href="{{ route('delivery.consignments.create', ['loading_transfer_id' => $transfer->id]) }}">Create delivery</a>@endif
                </div>
            @endforeach
        </div>
    @else
        <p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No unused loading transfer is ready. Start from Vehicle Store → Loading.</p>
    @endif
</section>

<section class="serial-card">
    <h2>Find deliveries</h2>
    <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <div><label for="date_from">From</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"></div>
        <div><label for="date_to">To</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"></div>
        <div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['loaded'=>'Loaded','in_transit'=>'In transit','arrived'=>'At customer','delivered'=>'Delivered','partial'=>'Partial','failed'=>'Failed'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="driver_id">Driver</label><select id="driver_id" name="driver_id"><option value="">All drivers</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected(request('driver_id')==$driver->id)>{{ $driver->name }}</option>@endforeach</select></div>
        <div><label for="vehicle_id">Vehicle</label><select id="vehicle_id" name="vehicle_id"><option value="">All vehicles</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(request('vehicle_id')==$vehicle->id)>{{ $vehicle->number }}</option>@endforeach</select></div>
        <div><label for="q">Search</label><input id="q" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Delivery, customer or reference"></div>
        <div class="sm:col-span-2 lg:col-span-6 flex gap-2"><button class="serial-btn">Apply filters</button><a href="{{ route('delivery.consignments.index') }}" class="serial-btn serial-secondary">Reset</a></div>
    </form>
</section>

<section class="serial-card" id="delivery-list">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h2 class="!mb-0">Deliveries</h2><p class="text-xs text-slate-500">{{ $consignments->total() }} matching record(s).</p></div></div>
    <div class="overflow-x-auto"><table id="delivery-consignments-table" class="serial-table"><thead><tr><th>Delivery no.</th><th>Order reference</th><th>Customer</th><th>Driver</th><th>Vehicle</th><th>Created</th><th>Status</th><th>Next action</th></tr></thead><tbody>
    @forelse($consignments as $item)
        @php($statusStyles = ['loaded'=>'bg-emerald-100 text-emerald-700','in_transit'=>'bg-indigo-100 text-indigo-700','arrived'=>'bg-fuchsia-100 text-fuchsia-700','delivered'=>'bg-violet-100 text-violet-700','partial'=>'bg-amber-100 text-amber-700','failed'=>'bg-rose-100 text-rose-700'])
        <tr>
            <td><a class="font-bold text-indigo-700" href="{{ route('delivery.consignments.show',$item) }}">{{ $item->number }}</a></td>
            <td>{{ $item->sales_order_reference ?: 'Manual delivery' }}</td><td>{{ $item->customer->name }}</td><td>{{ $item->loadingTransfer->driver?->name ?: 'Unassigned' }}</td><td>{{ $item->loadingTransfer->vehicle->number }}</td><td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
            <td><span class="rounded-full px-2 py-1 text-xs font-bold {{ $statusStyles[$item->status] ?? 'bg-slate-100 text-slate-700' }}">{{ ucwords(str_replace('_',' ',$item->status)) }}</span></td>
            <td><a class="font-bold text-indigo-700 underline" href="{{ route('delivery.consignments.show',$item) }}">{{ match($item->status) {'loaded'=>'Start delivery','in_transit'=>'Mark arrived','arrived'=>'Record receipt & POD',default=>'View POD'} }}</a></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-slate-500">No deliveries match these filters.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="mt-4">{{ $consignments->links() }}</div>
</section>

@php($deliveryTables = [['id'=>'delivery-consignments-table','title'=>'Delivery Dashboard','exportColumns'=>[0,1,2,3,4,5,6],'nonOrderable'=>[7],'order'=>[[5,'desc']],'emptyTable'=>'No deliveries found']])
@include('delivery.table-tools', compact('deliveryTables'))
@endsection
