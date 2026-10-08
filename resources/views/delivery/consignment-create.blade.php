@extends('layouts.app')
@section('title','New Delivery')
@section('content')
@include('serials.common')
<div class="flex items-center justify-between mb-4"><div><h1 class="text-xl font-bold">New delivery</h1><p class="text-sm text-slate-500">Use a completed loading transfer for one customer as the delivery's stock source.</p></div><a class="serial-btn serial-secondary" href="{{ route('delivery.consignments.index') }}">Back</a></div>
@if($errors->any())<div class="serial-card text-rose-700"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('delivery.consignments.store') }}" class="serial-card">@csrf
<div class="serial-grid">
<div><label for="loading_transfer_id">Loading transfer *</label><select id="loading_transfer_id" name="loading_transfer_id" required><option value="">Select unused loading transfer</option>@foreach($transfers as $transfer)<option value="{{ $transfer->id }}" @selected(old('loading_transfer_id',request('loading_transfer_id'))==$transfer->id)>DLV-{{ $transfer->id }} · {{ $transfer->vehicle->number }} · {{ $transfer->warehouse->name }} · {{ $transfer->lines->count() }} items</option>@endforeach</select></div>
<div><label for="customer_id">Customer *</label><select id="customer_id" name="customer_id" required><option value="">Select customer</option>@foreach($customers as $customer)@php($customerAddress = $customer->shipping_address ?: collect([$customer->address_line_1,$customer->address_line_2,$customer->city,$customer->state,$customer->country,$customer->zip_code])->filter()->implode(', '))<option value="{{ $customer->id }}" data-address="{{ $customerAddress }}" @selected(old('customer_id')==$customer->id)>{{ $customer->name }}</option>@endforeach</select></div>
<div><label for="scheduled_at">Scheduled departure</label><input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"></div>
</div><div class="mt-4"><label for="delivery_address">Delivery address *</label><input id="delivery_address" name="delivery_address" maxlength="500" required value="{{ old('delivery_address') }}"></div>
<div class="mt-4"><label for="notes">Instructions / notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000" class="w-full rounded-xl border border-slate-300 p-3">{{ old('notes') }}</textarea></div>
<div class="mt-5"><button class="serial-btn">Create delivery</button></div>
</form>
@endsection
@push('scripts')
<script>
AppPage.ready(() => {
    const customer = document.getElementById('customer_id');
    const address = document.getElementById('delivery_address');
    if (!customer || !address) return;
    customer.addEventListener('change', () => {
        if (!address.value.trim()) address.value = customer.selectedOptions[0]?.dataset.address || '';
    });
});
</script>
@endpush
