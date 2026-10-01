@extends('layouts.app')
@section('title',$driver->exists ? 'Edit Driver' : 'Add Driver')
@section('content')
@include('serials.common')
<h1 class="text-xl font-bold mb-5">{{ $driver->exists ? 'Edit Driver' : 'Add Driver' }}</h1>
<form class="serial-card" method="POST" action="{{ $driver->exists ? route('delivery.drivers.update',$driver) : route('delivery.drivers.store') }}">
@csrf @if($driver->exists) @method('PUT') @endif
<div class="serial-grid">
@foreach(['name'=>['Full name *','text',150],'phone'=>['Phone','tel',40],'email'=>['Email','email',255],'identity_number'=>['NIC / Identity number','text',50],'license_number'=>['Driving license number','text',80],'license_expires_at'=>['License expiry','date',10],'emergency_contact'=>['Emergency contact','text',150]] as $field=>[$label,$type,$max])
<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ old($field,$driver->$field) }}" @required($field==='name')></div>@endforeach
<div><label for="vehicle_id">Assign vehicle</label><select id="vehicle_id" name="vehicle_id"><option value="">Unassigned</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('vehicle_id',$driver->vehicles->first()?->id)==$vehicle->id) @disabled(($vehicle->drivers->isNotEmpty() && $vehicle->drivers->first()->id !== $driver->id) || !$vehicle->is_active)>{{ $vehicle->number }} — {{ $vehicle->name }}{{ $vehicle->drivers->isNotEmpty() ? ' ('.$vehicle->drivers->first()->name.')' : '' }}{{ !$vehicle->is_active ? ' (Inactive)' : '' }}</option>@endforeach</select><p class="text-xs text-slate-500 mt-2">One current vehicle per driver. Select Unassigned to release a vehicle.</p></div>
<div><label for="is_active">Status</label><select id="is_active" name="is_active"><option value="1" @selected(old('is_active',$driver->is_active))>Active</option><option value="0" @selected(!old('is_active',$driver->is_active))>Inactive</option></select></div>
</div>
@foreach(['address'=>'Address','notes'=>'Notes'] as $field=>$label)<div class="mt-5"><label for="{{ $field }}">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" rows="2" maxlength="2000" class="w-full border border-slate-300 rounded-xl p-3">{{ old($field,$driver->$field) }}</textarea></div>@endforeach
<div class="mt-5"><button class="serial-btn">{{ $driver->exists ? 'Update' : 'Save' }}</button> <a class="serial-btn serial-secondary" href="{{ route('delivery.drivers.index') }}">Cancel</a></div>
</form>
@endsection
