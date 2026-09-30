@extends('layouts.app')

@section('title', isset($user) ? 'Edit User' : 'Add User')
@section('subtitle', isset($user) ? 'Update user details and access' : 'Create a user and control their access')

@section('content')
@php
    $editing = isset($user);
    $input = 'mt-1 w-full rounded-xl border border-purple-100 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-purple-500 focus:ring-2 focus:ring-purple-100';
    $label = 'block text-xs font-bold text-slate-800';
@endphp
<form id="create-user-form" data-edit-user='@json($editing ? $user->loadMissing(["profile", "locations", "selectedContacts"]) : null)' action="{{ $editing ? route('users.profile.update', $user) : route('users.store') }}" method="POST" class="mx-auto max-w-6xl space-y-5">
    @csrf
    @if($editing) @method('PUT') @endif
    <div><h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $editing ? 'Edit User' : 'Add User' }}</h1><p class="mt-1 text-sm text-slate-500">{{ $editing ? 'Update this user’s profile, role and access settings.' : 'Create a user and control their access.' }}</p></div>
    <div id="user-form-errors" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"></div>

    <section class="rounded-2xl border border-slate-100 bg-white px-6 py-5 shadow-sm">
        <div class="grid gap-x-7 gap-y-4 md:grid-cols-12">
            <div class="md:col-span-2"><label class="{{ $label }}" for="prefix">Prefix:</label><select id="prefix" name="prefix" class="{{ $input }}"><option value="">Mr / Mrs / Miss</option>@foreach(['Mr','Mrs','Miss','Dr','Rev'] as $prefix)<option @selected(old('prefix')===$prefix)>{{ $prefix }}</option>@endforeach</select></div>
            <div class="md:col-span-5"><label class="{{ $label }}" for="first_name">First Name:*</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" maxlength="100" placeholder="First Name" required class="{{ $input }}"></div>
            <div class="md:col-span-5"><label class="{{ $label }}" for="last_name">Last Name:</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" maxlength="100" placeholder="Last Name" class="{{ $input }}"></div>
            <div class="md:col-span-4"><label class="{{ $label }}" for="email">Email:*</label><input id="email" name="email" value="{{ old('email') }}" type="email" maxlength="255" autocapitalize="none" placeholder="Email" required class="{{ $input }}"></div>
            <div class="flex items-center md:col-span-4 md:pt-5"><input type="hidden" name="status" value="suspended"><label class="inline-flex items-center gap-3 text-sm font-medium text-slate-800"><input id="status" name="status" value="active" type="checkbox" checked class="h-5 w-5 accent-purple-600"> Is active ? <span class="field-help" tabindex="0" aria-label="User status help" data-tooltip="Check to allow this user to use the system. Uncheck to keep the account inactive."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span></label></div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-100 bg-white px-6 py-5 shadow-sm">
        <h2 class="mb-8 text-lg font-bold text-slate-900">Roles and Permissions</h2>
        <input type="hidden" name="allow_login" value="0"><label class="mb-5 ml-5 inline-flex items-center gap-3 text-sm font-medium"><input id="allow_login" type="checkbox" name="allow_login" value="1" checked class="h-5 w-5 accent-purple-600"> Allow login</label>
        <div id="login-fields" class="grid gap-7 md:grid-cols-3">
            <div><label class="{{ $label }}" for="username">Username:</label><input id="username" name="username" value="{{ old('username') }}" maxlength="50" autocomplete="username" placeholder="Username" class="{{ $input }}"><p class="mt-1 text-[11px] text-slate-400">Leave blank to auto generate username</p></div>
            <div><label class="{{ $label }}" for="password">Password:*</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Password" class="{{ $input }}"></div>
            <div><label class="{{ $label }}" for="password_confirmation">Confirm Password:*</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" placeholder="Confirm Password" class="{{ $input }}"></div>
        </div>
        <div class="mt-5 max-w-md"><label class="{{ $label }}" for="role_id">Role:* <span class="field-help" tabindex="0" aria-label="Role help" data-tooltip="Choose the role that controls what this user can do in the system."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span></label><select id="role_id" name="role_id" required class="{{ $input }}"><option value="">Please Select</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id')==$role->id)>{{ $role->name }}</option>@endforeach</select></div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <h3 class="text-lg font-medium text-slate-800">Access locations <span class="field-help" tabindex="0" aria-label="Location access help" data-tooltip="Select the branches and warehouses this user is allowed to access."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span></h3>
            <div class="md:col-span-2">
                <input type="hidden" name="all_locations" value="0"><label class="inline-flex items-center gap-3 text-sm font-medium"><input id="all_locations" type="checkbox" name="all_locations" value="1" checked class="h-5 w-5 accent-purple-600"> All Locations <span class="field-help" tabindex="0" aria-label="All locations help" data-tooltip="This user can access every active business location. Untick it to choose individual locations."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span></label>
                <div id="location-list" class="mt-4 grid gap-3">
                    @forelse($locations as $location)<label class="flex items-center gap-3 text-sm"><input type="checkbox" name="location_ids[]" value="{{ $location->id }}" class="h-5 w-5 accent-purple-600"> <span>{{ $location->name }} ({{ $location->code }})</span></label>@empty<p class="text-sm text-rose-600">Create an active business location first.</p>@endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-100 bg-white px-6 py-5 shadow-sm">
        <h2 class="mb-8 text-lg font-bold text-slate-900">Sales</h2>
        <div class="grid max-w-3xl gap-7 md:grid-cols-2">
            <div><label class="{{ $label }}" for="commission_percent">Sales Commission Percentage (%): <span class="field-help" tabindex="0" aria-label="Sales commission help" data-tooltip="Commission percentage earned from the sales made by this user."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span></label><input id="commission_percent" name="commission_percent" value="{{ old('commission_percent') }}" placeholder="Sales Commission Percentage (%)" type="number" min="0" max="100" step="0.01" class="{{ $input }}"></div>
            <div><label class="{{ $label }}" for="max_sales_discount_percent">Max sales discount percent: <span class="field-help" tabindex="0" aria-label="Maximum discount help" data-tooltip="The highest discount percentage this user can give during a sale."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span></label><input id="max_sales_discount_percent" name="max_sales_discount_percent" value="{{ old('max_sales_discount_percent') }}" placeholder="Max sales discount percent" type="number" min="0" max="100" step="0.01" class="{{ $input }}"></div>
        </div>
        <div class="mt-8 ml-5">
            <input type="hidden" name="restrict_contacts" value="0"><label class="inline-flex items-center gap-3 text-sm font-medium"><input id="restrict_contacts" type="checkbox" name="restrict_contacts" value="1" class="h-5 w-5 accent-purple-600"> Allow Selected Contacts <span class="field-help" tabindex="0" aria-label="Selected contacts help" data-tooltip="Restrict this user to only the customers and suppliers selected below."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span></label>
            <div id="contact-list" class="mt-3 max-h-56 grid gap-2 overflow-y-auto rounded-xl border border-slate-200 p-3 sm:grid-cols-2" hidden>
                @forelse($contacts as $contact)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" class="accent-purple-600"> {{ $contact->name }} <small class="text-slate-400">{{ $contact->contact_id }}</small></label>@empty<p class="text-sm text-slate-500">No active contacts available.</p>@endforelse
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-100 bg-white px-6 py-5 shadow-sm">
        <h2 class="mb-8 text-lg font-bold text-slate-900">More Informations</h2>
        <div class="grid gap-4 md:grid-cols-4">
            <div><label class="{{ $label }}">Date of birth</label><input name="date_of_birth" type="date" max="{{ now()->toDateString() }}" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">Gender</label><select name="gender" class="{{ $input }}"><option value="">Select</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
            <div><label class="{{ $label }}">Marital status</label><select name="marital_status" class="{{ $input }}"><option value="">Select</option>@foreach(['single','married','divorced','widowed'] as $value)<option value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></div>
            <div><label class="{{ $label }}">Blood group</label><input name="blood_group" maxlength="10" class="{{ $input }}"></div>
            @foreach(['mobile'=>'Mobile Number:','alternate_contact'=>'Alternate contact number:','family_contact'=>'Family contact number:','facebook_link'=>'Facebook Link:','twitter_link'=>'Twitter Link:','social_media_1'=>'Social Media 1:','social_media_2'=>'Social Media 2:','custom_field_1'=>'Custom field 1:','custom_field_2'=>'Custom field 2:','custom_field_3'=>'Custom field 3:','custom_field_4'=>'Custom field 4:','guardian_name'=>'Guardian Name:','id_proof_name'=>'ID proof name:','id_proof_number'=>'ID proof number:'] as $name=>$text)
                <div><label class="{{ $label }}">{{ $text }}</label><input name="{{ $name }}" value="{{ old($name) }}" maxlength="255" placeholder="{{ rtrim($text, ':') }}" class="{{ $input }}"></div>
                @if($name === 'social_media_1')<div class="hidden md:block"></div><div class="hidden md:block"></div>@endif
            @endforeach
            <div class="md:col-span-2"><label class="{{ $label }}">Permanent address</label><textarea name="permanent_address" rows="3" maxlength="2000" class="{{ $input }}"></textarea></div>
            <div class="md:col-span-2"><label class="{{ $label }}">Current address</label><textarea name="current_address" rows="3" maxlength="2000" class="{{ $input }}"></textarea></div>
        </div>
        <h3 class="mt-7 border-t border-purple-100 pt-5 text-lg font-medium text-purple-800">Bank Details:</h3>
        <div class="mt-3 grid gap-7 md:grid-cols-4">
            @foreach(['account_holder_name'=>'Account holder name','account_number'=>'Account number','bank_name'=>'Bank name','bank_identifier_code'=>'Bank identifier code','bank_branch'=>'Branch','tax_payer_id'=>'Tax payer ID'] as $name=>$text)
                <div><label class="{{ $label }}">{{ $text }}@if($name === 'bank_identifier_code')<span class="field-help" tabindex="0" aria-label="Bank identifier code help" data-tooltip="A code that identifies the bank, such as an IFSC, SWIFT or local bank code."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>@endif@if($name === 'tax_payer_id')<span class="field-help" tabindex="0" aria-label="Tax payer ID help" data-tooltip="The employee tax identification number, for example NIC, TIN or PAN."><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>@endif</label><input name="{{ $name }}" value="{{ old($name) }}" maxlength="255" class="{{ $input }}"></div>
            @endforeach
        </div>
    </section>

    <div class="flex justify-center gap-3 pb-6"><a href="{{ $editing ? route('users.view', $user) : route('users.index') }}" class="rounded-full bg-slate-200 px-6 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-300">Cancel</a><button id="save-user" class="rounded-full bg-purple-600 px-8 py-2.5 text-sm font-bold text-white shadow-md shadow-purple-500/20 hover:bg-purple-700">{{ $editing ? 'Update' : 'Save' }}</button></div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('create-user-form');
    const editing = @json($editing);
    const editUser = @json($editing ? $user : null);
    const login = document.getElementById('allow_login');
    const allLocations = document.getElementById('all_locations');
    const contacts = document.getElementById('restrict_contacts');
    const toggle = (control, target, inverse = false) => {
        const visible = inverse ? !control.checked : control.checked;
        target.hidden = !visible;
        target.querySelectorAll('input,select').forEach(input => input.disabled = !visible);
    };
    const sync = () => {
        toggle(login, document.getElementById('login-fields'));
        const locationList = document.getElementById('location-list');
        locationList.hidden = false;
        locationList.classList.toggle('opacity-50', allLocations.checked);
        locationList.querySelectorAll('input').forEach(input => input.disabled = allLocations.checked);
        toggle(contacts, document.getElementById('contact-list'));
    };
    if (editing) {
        const profile = editUser.profile || {};
        const values = {...editUser, ...profile};
        Object.entries(values).forEach(([name, value]) => {
            if (value === null || value === undefined || ['id', 'password', 'role', 'profile', 'locations', 'selected_contacts'].includes(name)) return;
            const field = form.querySelector(`[name="${name}"]`);
            if (field && field.type !== 'checkbox') field.value = String(value).replace(' 00:00:00', '');
        });
        document.getElementById('status').checked = editUser.status === 'active';
        login.checked = !!editUser.allow_login;
        allLocations.checked = !!editUser.all_locations;
        contacts.checked = !!editUser.restrict_contacts;
        (editUser.locations || []).forEach(location => { const field = form.querySelector(`[name="location_ids[]"][value="${location.id}"]`); if (field) field.checked = true; });
        (editUser.selected_contacts || []).forEach(contact => { const field = form.querySelector(`[name="contact_ids[]"][value="${contact.id}"]`); if (field) field.checked = true; });
    }
    [login, allLocations, contacts].forEach(control => control.addEventListener('change', sync));
    sync();

    // Help icons sit inside labels, so prevent a click on the icon from changing its checkbox.
    form.querySelectorAll('.field-help').forEach(help => help.addEventListener('click', event => event.preventDefault()));

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = document.getElementById('save-user');
        const errors = document.getElementById('user-form-errors');
        errors.hidden = true; button.disabled = true; button.textContent = 'Saving…';
        try {
            const result = await AppErrors.request(form.action, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}});
            Turbo.visit(result.redirect || @json(route('users.index')));
        } catch (error) {
            const messages = error.errors ? Object.values(error.errors).flat() : [error.message];
            errors.replaceChildren(...messages.map(message => { const item=document.createElement('p'); item.textContent=message; return item; }));
            errors.hidden = false; errors.scrollIntoView({behavior:'smooth', block:'center'});
        } finally { button.disabled = false; button.textContent = editing ? 'Update' : 'Save'; }
    });
})();
</script>
@endpush
