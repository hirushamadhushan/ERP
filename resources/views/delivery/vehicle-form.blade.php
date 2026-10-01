@extends('layouts.app')
@section('title',$vehicle->exists ? 'Edit Vehicle' : 'Add Vehicle')
@section('content')
@include('serials.common')
<h1 class="text-xl font-bold mb-5">{{ $vehicle->exists ? 'Edit Vehicle' : 'Add Vehicle' }}</h1>
<form method="POST" action="{{ $vehicle->exists ? route('delivery.vehicles.update',$vehicle) : route('delivery.vehicles.store') }}" class="serial-card">
@csrf @if($vehicle->exists) @method('PUT') @endif
<div class="serial-grid">
@foreach(['number'=>['Vehicle number *','text',40],'name'=>['Vehicle name *','text',150],'make'=>['Make','text',100],'model'=>['Model','text',100],'year'=>['Year','number',4],'chassis_number'=>['Chassis number','text',100],'engine_number'=>['Engine number','text',100],'insurance_expires_at'=>['Insurance expiry','date',10],'revenue_license_expires_at'=>['Revenue license expiry','date',10]] as $field=>[$label,$type,$max])
<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ old($field,$vehicle->$field) }}" @required(in_array($field,['number','name'])) @if($field==='year') min="1900" max="2100" @endif></div>
@endforeach
<div><label for="fuel_type">Fuel type</label><select id="fuel_type" name="fuel_type"><option value="">Select fuel type</option>@foreach(['petrol','diesel','electric','hybrid','other'] as $fuel)<option value="{{ $fuel }}" @selected(old('fuel_type',$vehicle->fuel_type)===$fuel)>{{ ucfirst($fuel) }}</option>@endforeach</select></div>
<div><label for="is_active">Status</label><select id="is_active" name="is_active"><option value="1" @selected(old('is_active',$vehicle->is_active))>Active</option><option value="0" @selected(!old('is_active',$vehicle->is_active))>Inactive</option></select></div>
</div><div class="mt-5"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="2000" rows="3" class="w-full border border-slate-300 rounded-xl p-3">{{ old('notes',$vehicle->notes) }}</textarea></div>
<p class="text-sm text-slate-500 my-4">A dedicated vehicle stock location is created automatically. Inactive vehicles can still be unloaded.</p>
<button class="serial-btn">{{ $vehicle->exists ? 'Update' : 'Save' }}</button> <a href="{{ route('delivery.vehicles.index') }}" class="serial-btn serial-secondary">Cancel</a>
</form>
@endsection
