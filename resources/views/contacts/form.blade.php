<dialog id="contact-dialog" aria-labelledby="contact-dialog-title" class="bg-white text-slate-800 shadow-2xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-purple-100 bg-purple-50 sticky top-0 z-10">
        <h2 id="contact-dialog-title" class="text-base font-bold text-slate-900">Add a new contact</h2>
        <button type="button" data-close-contact class="w-8 h-8 rounded-lg text-slate-500 hover:bg-purple-100" aria-label="Close contact form"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <form id="contact-form" method="POST" action="{{ old('_contact_id') ? route('contacts.update', old('_contact_id')) : route('contacts.store') }}">
        @csrf
        <input type="hidden" name="_method" value="{{ old('_contact_id') ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_contact_id" value="{{ old('_contact_id') }}">
        <div class="p-5 sm:p-6 space-y-6">
            @if($errors->any())
                <div id="contact-validation-errors" role="alert" class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 p-4 text-sm">
                    <p class="font-semibold mb-2">Please correct the following:</p><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <fieldset id="contact-fields" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div><label for="contact-type" class="{{ $labelClass }}">Contact type <span class="text-rose-500">*</span></label><select name="type" id="contact-type" class="{{ $inputClass }}" required>@foreach(\App\Models\Contact::FORM_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type', $type) === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div><span class="{{ $labelClass }}">Contact category</span><div class="flex flex-wrap gap-4 py-3 text-sm">@foreach(['individual' => 'Individual', 'business' => 'Business'] as $value => $label)<label class="inline-flex gap-2 items-center"><input type="radio" name="entity_type" value="{{ $value }}" @checked(old('entity_type', 'individual') === $value) class="accent-purple-600">{{ $label }}</label>@endforeach</div></div>
                    @include('contacts.field', ['field' => 'contact_id', 'label' => 'Contact ID', 'maxlength' => 60, 'help' => 'Leave empty to autogenerate'])
                    @include('contacts.field', ['field' => 'name', 'label' => 'Name', 'required' => true])
                    <div id="business-name-field">@include('contacts.field', ['field' => 'business_name', 'label' => 'Business Name'])</div>
                    <div data-contact-types="customer both"><label for="contact-customer_group" class="{{ $labelClass }}">Customer Group</label><select id="contact-customer_group" name="customer_group" class="{{ $inputClass }}"><option value="">None</option>@foreach($groups as $group)<option value="{{ $group }}" @selected(old('customer_group') === $group)>{{ $group }}</option>@endforeach</select><a href="{{ route('contacts.groups.index') }}" class="inline-block mt-1 text-xs text-purple-600 hover:underline">Manage customer groups</a></div>
                    <div data-contact-types="commission">@include('contacts.field', ['field' => 'commission_percentage', 'label' => 'Commission (%)', 'inputType' => 'number', 'default' => 0, 'max' => 100])</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    @include('contacts.field', ['field' => 'mobile', 'label' => 'Mobile', 'inputType' => 'tel', 'maxlength' => 50, 'required' => true])
                    @include('contacts.field', ['field' => 'alternate_number', 'label' => 'Alternate contact number', 'inputType' => 'tel', 'maxlength' => 50])
                    @include('contacts.field', ['field' => 'landline', 'label' => 'Landline', 'inputType' => 'tel', 'maxlength' => 50])
                    @include('contacts.field', ['field' => 'email', 'label' => 'Email', 'inputType' => 'email'])
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div><label for="contact-assigned_to" class="{{ $labelClass }}">Assigned to</label><select name="assigned_to" id="contact-assigned_to" class="{{ $inputClass }}"><option value="">Unassigned</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected(old('assigned_to') == $assignee->id)>{{ $assignee->name }}</option>@endforeach</select></div>
                    <div><label for="contact-status" class="{{ $labelClass }}">Status</label><select name="status" id="contact-status" class="{{ $inputClass }}"><option value="active" @selected(old('status', 'active') === 'active')>Active</option><option value="inactive" @selected(old('status') === 'inactive')>Inactive</option></select></div>
                </div>
                <details id="contact-more" class="group/more">
                    <summary class="mx-auto w-fit list-none [&::-webkit-details-marker]:hidden cursor-pointer {{ in_array($type, ['customer', 'supplier']) ? $primaryClass : $secondaryClass }}">More information <i class="bi bi-chevron-down group-open/more:rotate-180 transition-transform" aria-hidden="true"></i></summary>
                    <div class="mt-6 pt-6 border-t border-slate-200 space-y-6">
                        <div data-contact-types="customer supplier both" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                            @include('contacts.field', ['field' => 'tax_number', 'label' => 'Tax number', 'maxlength' => 100])
                            @include('contacts.field', ['field' => 'opening_balance', 'label' => 'Opening Balance', 'inputType' => 'number', 'default' => 0])
                            <div>
                                <label for="contact-pay_term" class="{{ $labelClass }}">Pay term <i class="bi bi-info-circle text-purple-500" aria-hidden="true"></i></label>
                                <div class="flex gap-2">
                                    <input type="number" id="contact-pay_term" name="pay_term" min="0" max="100000" step="1" value="{{ old('pay_term') }}" placeholder="Pay term" aria-describedby="contact-pay-term-help" class="{{ $inputClass }} min-w-0">
                                    <select name="pay_term_unit" id="contact-pay_term_unit" aria-label="Pay term unit" aria-describedby="contact-pay-term-help" class="{{ $inputClass }} min-w-0"><option value="">Please Select</option><option value="months" @selected(old('pay_term_unit') === 'months')>Months</option><option value="days" @selected(old('pay_term_unit') === 'days')>Days</option></select>
                                </div>
                                <p id="contact-pay-term-help" class="mt-1 text-xs text-slate-400">Enter the payment period and select months or days.</p>
                            </div>
                            <div data-contact-types="customer both">@include('contacts.field', ['field' => 'credit_limit', 'label' => 'Credit Limit', 'inputType' => 'number', 'help' => 'Keep blank for no limit'])</div>
                            <div data-contact-types="customer both">@include('contacts.field', ['field' => 'opening_due_cans', 'label' => 'Opening Current Due Empty Cans', 'inputType' => 'number', 'step' => 1, 'default' => 0, 'help' => 'Manual carry-forward due cans for this customer'])</div>
                        </div>
                        <div data-contact-types="customer supplier both">@include('contacts.field', ['field' => 'date_of_birth', 'label' => 'Date of birth', 'inputType' => 'date'])</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-6 border-t border-slate-200">
                            @include('contacts.field', ['field' => 'address_line_1', 'label' => 'Address line 1'])
                            @include('contacts.field', ['field' => 'address_line_2', 'label' => 'Address line 2'])
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                            @include('contacts.field', ['field' => 'city', 'label' => 'City', 'maxlength' => 100])
                            @include('contacts.field', ['field' => 'state', 'label' => 'State', 'maxlength' => 100])
                            @include('contacts.field', ['field' => 'country', 'label' => 'Country', 'maxlength' => 100])
                            @include('contacts.field', ['field' => 'zip_code', 'label' => 'Zip Code', 'maxlength' => 30])
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 pt-6 border-t border-slate-200">
                            @for($i = 0; $i < 10; $i++)<div><label for="contact-custom-{{ $i }}" class="{{ $labelClass }}">Custom Field {{ $i + 1 }}</label><input id="contact-custom-{{ $i }}" name="custom_fields[{{ $i }}]" value="{{ old('custom_fields.'.$i) }}" maxlength="255" placeholder="Custom Field {{ $i + 1 }}" class="{{ $inputClass }}"></div>@endfor
                        </div>
                        <div data-contact-types="commission" class="pt-6 border-t border-slate-200 {{ $type !== 'commission' ? 'hidden' : '' }}"><div><label for="contact-shipping_address" class="{{ $labelClass }}">Shipping Address</label><textarea id="contact-shipping_address" name="shipping_address" rows="2" maxlength="2000" class="{{ $inputClass }}" placeholder="Shipping address">{{ old('shipping_address') }}</textarea></div></div>
                    </div>
                </details>
            </fieldset>
        </div>
        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-purple-100 bg-white sticky bottom-0">
            <button type="button" data-close-contact class="{{ $secondaryClass }}">Close</button>
            <button id="save-contact" type="submit" class="{{ $primaryClass }}">Save</button>
        </div>
    </form>
</dialog>
