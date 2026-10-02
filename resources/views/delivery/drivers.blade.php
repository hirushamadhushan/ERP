@extends('layouts.app')
@section('title','Delivery Drivers')
@section('content')
@include('serials.common')
<section class="serial-card"><div class="flex justify-between items-center mb-5"><div><h1 class="text-xl font-bold">Drivers</h1><p class="text-sm text-slate-500">Contact details, driving licenses and vehicle assignments.</p></div>@if(auth()->user()->canUseDelivery('delivery.manage'))<button id="add-driver" type="button" class="serial-btn">+ Add Driver</button>@endif</div>
<div class="overflow-x-auto"><table id="delivery-drivers-table" data-async-table class="serial-table"><thead><tr><th>Name</th><th>Phone</th><th>License number</th><th>License expiry</th><th>Assigned vehicle</th><th>Status</th><th>Action</th></tr></thead><tbody>
@foreach($drivers as $driver)
@php
    $driverData = $driver->only(['id', 'name', 'phone', 'email', 'identity_number', 'license_number', 'license_expires_at', 'address', 'emergency_contact', 'notes', 'is_active']) + ['vehicle_id' => $driver->vehicles->first()?->id];
@endphp
<tr><td>{{ $driver->name }}</td><td>{{ $driver->phone ?? '—' }}</td><td>{{ $driver->license_number ?? '—' }}</td><td>{{ $driver->license_expires_at ?? '—' }}</td><td>{{ $driver->vehicles->first()?->number ?? 'Unassigned' }}</td><td>{{ $driver->is_active ? 'Active' : 'Inactive' }}</td><td>@if(auth()->user()->canUseDelivery('delivery.manage'))<button type="button" class="edit-driver serial-btn" data-driver='{{ json_encode($driverData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}' data-url="{{ route('delivery.drivers.update',$driver) }}">Edit / Assign</button>@endif</td></tr>
@endforeach
</tbody></table></div><div id="driver-pagination" data-async-region class="mt-5">{{ $drivers->links() }}</div></section>
@if(auth()->user()->canUseDelivery('delivery.manage'))
<dialog id="driver-dialog" aria-labelledby="driver-dialog-title" class="delivery-dialog">
<div class="flex justify-between items-center px-6 py-4 bg-purple-50 border-b border-purple-100"><div><h2 id="driver-dialog-title" class="text-base font-bold text-slate-800">Add Driver</h2><p class="text-xs text-slate-500">Enter driver details and optionally assign one active vehicle.</p></div><button type="button" class="delivery-dialog-close" aria-label="Close driver form">×</button></div>
<form data-async-form id="driver-form" method="POST" action="{{ route('delivery.drivers.store') }}">@csrf<input type="hidden" name="_method" value="POST">
<div class="p-6 grid gap-4 sm:grid-cols-2">
@foreach(['name'=>['Full name *','text',150],'phone'=>['Phone','tel',40],'email'=>['Email','email',255],'identity_number'=>['NIC / Identity number','text',50],'license_number'=>['Driving license number','text',80],'license_expires_at'=>['License expiry','date',10],'emergency_contact'=>['Emergency contact','text',150]] as $field=>[$label,$type,$max])
<div><label for="driver-{{ $field }}">{{ $label }}</label><input id="driver-{{ $field }}" name="{{ $field }}" type="{{ $type }}" maxlength="{{ $max }}" @required($field==='name')></div>
@endforeach
<div id="driver-vehicle-region" data-async-region><label for="driver-vehicle">Assign vehicle</label><select id="driver-vehicle" name="vehicle_id"><option value="">Unassigned</option>@foreach($vehicles as $vehicle)@php($assignedDriver = $vehicle->drivers->first())<option value="{{ $vehicle->id }}" data-assigned-driver-id="{{ $assignedDriver?->id }}" data-inactive="{{ $vehicle->is_active ? '0' : '1' }}" @disabled($assignedDriver || !$vehicle->is_active)>{{ $vehicle->number }} — {{ $vehicle->name }}{{ $assignedDriver ? ' (Assigned to '.$assignedDriver->name.')' : '' }}{{ !$vehicle->is_active ? ' (Inactive)' : '' }}</option>@endforeach</select><p class="text-xs text-slate-500 mt-2">Vehicles shown in light grey are already assigned or inactive and cannot be selected.</p></div>
<div><label for="driver-is-active">Status</label><select id="driver-is-active" name="is_active"><option value="1" selected>Active</option><option value="0">Inactive</option></select></div>
<div class="sm:col-span-2"><label for="driver-address">Address</label><textarea id="driver-address" name="address" maxlength="2000" rows="2"></textarea></div>
<div class="sm:col-span-2"><label for="driver-notes">Notes</label><textarea id="driver-notes" name="notes" maxlength="2000" rows="2"></textarea></div>
<div class="sm:col-span-2 flex justify-end gap-2"><button class="serial-btn" type="submit">Save Driver</button><button class="serial-btn serial-secondary delivery-dialog-cancel" type="button">Cancel</button></div>
</div></form></dialog>
@endif
@include('delivery.table-tools', ['deliveryTables' => [['id'=>'delivery-drivers-table','title'=>'Delivery Drivers','exportColumns'=>[0,1,2,3,4,5],'nonOrderable'=>[6],'order'=>[[0,'asc']],'emptyTable'=>'No drivers found']]])
@endsection
@push('scripts')
<style>.delivery-dialog{margin:auto;padding:0;border:0;border-radius:1rem;width:calc(100% - 2rem);max-width:52rem;max-height:calc(100dvh - 2rem);overflow:auto;background:#fff;color:#0f172a;box-shadow:0 25px 60px #0f172a33}.delivery-dialog::backdrop{background:rgb(15 23 42/.55);backdrop-filter:blur(3px)}.delivery-dialog label{display:block;margin-bottom:5px;font-size:12px;font-weight:700;color:#334155}.delivery-dialog input,.delivery-dialog select,.delivery-dialog textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:9px 11px;font-size:13px;background:#fff}.delivery-dialog select option:disabled{color:#94a3b8;background:#f8fafc}.delivery-dialog input:focus,.delivery-dialog select:focus,.delivery-dialog textarea:focus{outline:0;border-color:#9333ea;box-shadow:0 0 0 2px #9333ea22}.delivery-dialog-close{width:32px;height:32px;border-radius:9px;color:#64748b;font-size:23px}.delivery-dialog-close:hover{background:#ede9fe}</style>
<script>
AppPage.ready(()=>{
 const dialog=document.getElementById('driver-dialog'),form=document.getElementById('driver-form');if(!dialog||!form)return;
 const fields=['name','phone','email','identity_number','license_number','license_expires_at','emergency_contact','vehicle_id','address','notes','is_active'];
 const clearErrors=()=>form.querySelectorAll('[data-async-errors]').forEach(node=>node.remove());
 const setVehicleAvailability=driverId=>{const select=form.elements.vehicle_id;if(!select)return;Array.from(select.options).forEach(option=>{if(!option.value)return;const assignedDriverId=option.dataset.assignedDriverId;option.disabled=option.dataset.inactive==='1'||(assignedDriverId!==''&&assignedDriverId!==String(driverId??''));});};
 const open=(data=null,url=null)=>{form.reset();clearErrors();form.action=url||@json(route('delivery.drivers.store'));form.elements._method.value=data?'PUT':'POST';setVehicleAvailability(data?.id);fields.forEach(key=>{const input=form.elements[key];if(input)input.value=data?(data[key]??''):(key==='is_active'?'1':'');});document.getElementById('driver-dialog-title').textContent=data?'Edit Driver':'Add Driver';dialog.showModal();form.elements.name.focus();};
 document.getElementById('add-driver')?.addEventListener('click',()=>open());
 document.getElementById('delivery-drivers-table')?.addEventListener('click',event=>{const button=event.target.closest('.edit-driver');if(button)open(JSON.parse(button.dataset.driver),button.dataset.url);});
 dialog.querySelectorAll('.delivery-dialog-close,.delivery-dialog-cancel').forEach(button=>button.addEventListener('click',()=>dialog.close()));
 dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
 document.addEventListener('app:form-saved',event=>{if(event.detail.form===form)dialog.close();},{signal:AppPage.signal});
});
</script>
@endpush
