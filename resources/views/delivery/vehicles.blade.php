@extends('layouts.app')
@section('title','Delivery Vehicles')
@section('content')
@include('serials.common')
<section class="serial-card">
<div class="flex justify-between items-center mb-5"><div><h1 class="text-xl font-bold">Vehicles</h1><p class="text-sm text-slate-500">Fleet details and assigned drivers.</p></div>@if(auth()->user()->canUseDelivery('delivery.manage'))<button id="add-vehicle" type="button" class="serial-btn">+ Add Vehicle</button>@endif</div>
<div class="overflow-x-auto"><table id="delivery-vehicles-table" data-async-table class="serial-table"><thead><tr><th>Vehicle number</th><th>Name</th><th>Brand / Model</th><th>Driver</th><th>Insurance expires</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@foreach($vehicles as $vehicle)
@php
    $vehicleData = $vehicle->only(['id', 'number', 'name', 'brand', 'model', 'year', 'fuel_type', 'chassis_number', 'engine_number', 'insurance_expires_at', 'revenue_license_expires_at', 'notes', 'is_active']);
@endphp
<tr><td class="font-bold">{{ $vehicle->number }}</td><td>{{ $vehicle->name }}</td><td>{{ trim($vehicle->brand.' '.$vehicle->model) ?: '—' }}</td><td>{{ $vehicle->drivers->first()?->name ?? 'Unassigned' }}</td><td>{{ $vehicle->insurance_expires_at ?? '—' }}</td><td>{{ $vehicle->is_active ? 'Active' : 'Inactive' }}</td><td class="whitespace-nowrap">@if(auth()->user()->canUseDelivery('delivery.manage'))<button type="button" class="edit-vehicle serial-btn" data-vehicle='{{ json_encode($vehicleData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}' data-url="{{ route('delivery.vehicles.update',$vehicle) }}">Edit</button>@endif <a class="serial-btn serial-secondary" href="{{ route('delivery.store',['vehicle_id'=>$vehicle->id]) }}">Stock</a></td></tr>
@endforeach
</tbody></table></div><div id="vehicle-pagination" data-async-region class="mt-5">{{ $vehicles->links() }}</div></section>
@if(auth()->user()->canUseDelivery('delivery.manage'))
<dialog id="vehicle-dialog" aria-labelledby="vehicle-dialog-title" class="delivery-dialog">
    <form data-async-form id="vehicle-form" method="POST" action="{{ route('delivery.vehicles.store') }}" class="flex flex-col flex-1 overflow-hidden m-0">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <div class="delivery-dialog-header flex justify-between items-center px-6 py-4 bg-white border-b border-slate-200 shrink-0">
            <div>
                <h2 id="vehicle-dialog-title" class="text-base font-bold text-slate-900 mb-0.5">Add Vehicle</h2>
                <p class="text-xs text-slate-500 mb-0">Vehicle number and name are required.</p>
            </div>
            <button type="button" class="delivery-dialog-close" aria-label="Close vehicle form">&times;</button>
        </div>
        <div class="delivery-dialog-body p-6 grid gap-4 sm:grid-cols-2">
            @foreach(['number'=>['Vehicle number *','text',40],'name'=>['Vehicle name *','text',150],'brand'=>['Brand','text',100],'model'=>['Model','text',100],'year'=>['Year','number',4],'chassis_number'=>['Chassis number','text',100],'engine_number'=>['Engine number','text',100],'insurance_expires_at'=>['Insurance expiry','date',10],'revenue_license_expires_at'=>['Revenue license expiry','date',10]] as $field=>[$label,$type,$max])
                <div>
                    <label for="vehicle-{{ $field }}">{{ $label }}</label>
                    <input id="vehicle-{{ $field }}" name="{{ $field }}" type="{{ $type }}" maxlength="{{ $max }}" @required(in_array($field,['number','name'])) @if($field==='year') min="1900" max="2100" @endif>
                </div>
            @endforeach
            <div>
                <label for="vehicle-fuel-type">Fuel type</label>
                <select id="vehicle-fuel-type" name="fuel_type">
                    <option value="">Select fuel type</option>
                    @foreach(['petrol','diesel','electric','hybrid','other'] as $fuel)
                        <option value="{{ $fuel }}">{{ ucfirst($fuel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="vehicle-is-active">Status</label>
                <select id="vehicle-is-active" name="is_active">
                    <option value="1" selected>Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="vehicle-notes">Notes</label>
                <textarea id="vehicle-notes" name="notes" maxlength="2000" rows="3"></textarea>
            </div>
        </div>
        <div class="delivery-dialog-footer">
            <button type="button" class="delivery-dialog-cancel btn-delivery-cancel">
                Cancel
            </button>
            <button id="save-vehicle-btn" type="submit" class="btn-delivery-save">
                <i class="bi bi-check2"></i> <span id="save-vehicle-text">Save Vehicle</span>
            </button>
        </div>
    </form>
</dialog>
@endif
@include('delivery.table-tools', ['deliveryTables' => [['id'=>'delivery-vehicles-table','title'=>'Delivery Vehicles','exportColumns'=>[0,1,2,3,4,5],'nonOrderable'=>[6],'order'=>[[0,'asc']],'emptyTable'=>'No vehicles found']]])
@endsection
@push('scripts')
<style>
.delivery-dialog {
    margin: auto;
    padding: 0;
    border: 0;
    border-radius: 0 !important;
    width: calc(100% - 2rem);
    max-width: 52rem;
    max-height: min(85vh, 680px) !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    background: #ffffff !important;
    color: #0f172a;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25) !important;
}
.delivery-dialog form {
    display: flex !important;
    flex-direction: column !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
    overflow: hidden !important;
    margin: 0 !important;
}
.delivery-dialog-header {
    background: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 18px 24px !important;
    flex-shrink: 0 !important;
    position: relative !important;
    z-index: 10 !important;
}
.delivery-dialog-body {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    padding: 20px 24px !important;
    position: relative !important;
    z-index: 1 !important;
}
.delivery-dialog-footer {
    background: #f8fafc !important;
    border-top: 1px solid #e2e8f0 !important;
    padding: 14px 24px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 12px !important;
    flex-shrink: 0 !important;
    position: relative !important;
    z-index: 10 !important;
}
.delivery-dialog-footer button.btn-delivery-cancel {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 8px 18px !important;
    border-radius: 10px !important;
    border: 1px solid #cbd5e1 !important;
    background: #ffffff !important;
    color: #475569 !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}
.delivery-dialog-footer button.btn-delivery-cancel:hover {
    background: #f1f5f9 !important;
    color: #0f172a !important;
    border-color: #94a3b8 !important;
}
.delivery-dialog-footer button.btn-delivery-save {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    padding: 8px 22px !important;
    border-radius: 10px !important;
    border: none !important;
    background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
    color: #ffffff !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    box-shadow: 0 3px 10px rgba(139, 92, 246, 0.3) !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}
.delivery-dialog-footer button.btn-delivery-save:hover {
    filter: brightness(1.08) !important;
    box-shadow: 0 4px 14px rgba(139, 92, 246, 0.4) !important;
    transform: translateY(-1px);
}
.delivery-dialog::backdrop {
    background: rgb(15 23 42 / .55);
    backdrop-filter: blur(3px);
}
.delivery-dialog label {
    display: block;
    margin-bottom: 5px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}
.delivery-dialog input,
.delivery-dialog select,
.delivery-dialog textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 9px 11px;
    font-size: 13px;
    background: #fff;
}
.delivery-dialog input:focus,
.delivery-dialog select:focus,
.delivery-dialog textarea:focus {
    outline: 0;
    border-color: #9333ea;
    box-shadow: 0 0 0 2px #9333ea22;
}
.delivery-dialog-close {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    color: #64748b;
    font-size: 23px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: transparent;
    border: none;
}
.delivery-dialog-close:hover {
    background: #ede9fe;
    color: #581c87;
}
</style>
<script>
AppPage.ready(()=>{
 const dialog=document.getElementById('vehicle-dialog'),form=document.getElementById('vehicle-form');if(!dialog||!form)return;
 const fields=['number','name','brand','model','year','fuel_type','chassis_number','engine_number','insurance_expires_at','revenue_license_expires_at','notes','is_active'];
 const clearErrors=()=>form.querySelectorAll('[data-async-errors]').forEach(node=>node.remove());
 const open=(data=null,url=null)=>{
     form.reset();
     clearErrors();
     form.action=url||@json(route('delivery.vehicles.store'));
     form.elements._method.value=data?'PUT':'POST';
     fields.forEach(key=>{const input=form.elements[key];if(input)input.value=data?(data[key]??''):(key==='is_active'?'1':'');});
     document.getElementById('vehicle-dialog-title').textContent=data?'Edit Vehicle':'Add Vehicle';
     const saveText=document.getElementById('save-vehicle-text');
     if(saveText)saveText.textContent=data?'Update Vehicle':'Save Vehicle';
     dialog.showModal();
     form.elements.number.focus();
 };
 document.getElementById('add-vehicle')?.addEventListener('click',()=>open());
 document.getElementById('delivery-vehicles-table')?.addEventListener('click',event=>{const button=event.target.closest('.edit-vehicle');if(button)open(JSON.parse(button.dataset.vehicle),button.dataset.url);});
 dialog.querySelectorAll('.delivery-dialog-close,.delivery-dialog-cancel').forEach(button=>button.addEventListener('click',()=>dialog.close()));
 dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
 document.addEventListener('app:form-saved',event=>{if(event.detail.form===form)dialog.close();},{signal:AppPage.signal});
});
</script>
@endpush
