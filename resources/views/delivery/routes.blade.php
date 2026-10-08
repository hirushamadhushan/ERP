@extends('layouts.app')
@section('title','Delivery Routes')
@section('content')
@include('serials.common')
@include('delivery.header',['activeStep'=>4])
<section class="serial-card">
 <div class="mb-5"><h1 class="text-xl font-bold">Multi-customer routes</h1><p class="text-sm text-slate-500">Group loaded deliveries for the same vehicle into an ordered run. Each customer keeps a separate POD and stock allocation.</p></div>
 @if(auth()->user()->canUseDelivery('delivery.route.manage'))
 <form method="POST" action="{{ route('delivery.routes.store') }}" class="grid gap-4 lg:grid-cols-3">@csrf
  <div class="lg:col-span-2"><label class="text-xs font-bold">Stops in travel order (minimum 2)</label><div class="mt-2 max-h-64 space-y-2 overflow-auto rounded-xl border p-3">
   @forelse($deliveries as $delivery)<label class="flex items-center gap-3 rounded-lg border p-3"><input type="checkbox" name="consignment_ids[]" value="{{ $delivery->id }}"><span><b>{{ $delivery->number }}</b> · {{ $delivery->customer->name }}<small class="block text-slate-500">{{ $delivery->loadingTransfer->vehicle->number }} · {{ $delivery->delivery_address }}</small></span></label>@empty<p class="text-sm text-slate-500">No unassigned loaded deliveries are available.</p>@endforelse
  </div></div>
  <div><label class="text-xs font-bold">Scheduled departure</label><input type="datetime-local" name="scheduled_at" class="mt-2 w-full rounded-xl border-slate-300"><label class="mt-3 block text-xs font-bold">Route notes</label><textarea name="notes" rows="3" class="mt-2 w-full rounded-xl border-slate-300"></textarea><button class="serial-btn mt-3" @disabled($deliveries->count()<2)>Create route</button></div>
 </form>@endif
</section>
<section class="serial-card"><h2 class="mb-4 text-lg font-bold">Route history</h2><div class="space-y-4">
@forelse($routes as $route)<article class="rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap justify-between gap-3"><div><b>{{ $route->number }}</b> · {{ $route->vehicle->number }} <span class="rounded-full bg-violet-100 px-2 py-1 text-xs">{{ ucfirst(str_replace('_',' ',$route->status)) }}</span></div>@if($route->status==='planned'&&auth()->user()->canUseDelivery('delivery.dispatch'))<form method="POST" action="{{ route('delivery.routes.depart',$route) }}">@csrf<button class="serial-btn">Depart full route</button></form>@endif</div><ol class="mt-3 grid gap-2 lg:grid-cols-2">@foreach($route->stops as $stop)<li class="rounded-lg bg-slate-50 p-3 text-sm"><b>{{ $stop->sequence }}. {{ $stop->consignment->customer->name }}</b><br><a class="text-violet-700 underline" href="{{ route('delivery.consignments.show',$stop->consignment) }}">{{ $stop->consignment->number }}</a> · {{ ucfirst(str_replace('_',' ',$stop->status)) }}</li>@endforeach</ol></article>@empty<p class="text-sm text-slate-500">No routes created yet.</p>@endforelse
</div>{{ $routes->links() }}</section>
@endsection
