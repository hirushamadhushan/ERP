<div class="{{ $span ?? '' }}">
    <label for="contact-{{ $field }}" class="{{ $labelClass }}">{{ $label }} @if($required ?? false)<span class="text-rose-500">*</span>@endif</label>
    @php
        $fieldIcon = in_array($type, ['customer', 'supplier']) ? ([
            'contact_id' => 'bi-person-badge', 'mobile' => 'bi-phone',
            'alternate_number' => 'bi-telephone-fill', 'landline' => 'bi-telephone-fill',
            'email' => 'bi-envelope-fill', 'tax_number' => 'bi-info-circle',
            'opening_balance' => 'bi-cash', 'credit_limit' => 'bi-cash', 'city' => 'bi-geo-alt-fill',
            'state' => 'bi-geo-alt-fill', 'country' => 'bi-globe', 'zip_code' => 'bi-geo-alt-fill',
        ][$field] ?? null) : null;
    @endphp
    <div class="relative">
        @if($fieldIcon)<span class="absolute inset-y-0 left-0 w-10 flex items-center justify-center text-slate-500 pointer-events-none"><i class="bi {{ $fieldIcon }}" aria-hidden="true"></i></span>@endif
        <input id="contact-{{ $field }}" name="{{ $field }}" type="{{ $inputType ?? 'text' }}" value="{{ old($field, $default ?? '') }}" class="{{ $inputClass }}" @if($fieldIcon) style="padding-left: 2.5rem" @endif @if($required ?? false) required @endif @if(($inputType ?? '') === 'number') min="0" step="{{ $step ?? '0.01' }}" @endif @if(isset($max)) max="{{ $max }}" @endif maxlength="{{ $maxlength ?? 255 }}" placeholder="{{ $placeholder ?? $label }}">
    </div>
    @if(isset($help))<p class="mt-1 text-xs text-slate-400">{{ $help }}</p>@endif
</div>
