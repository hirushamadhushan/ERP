@extends('layouts.app')
@section('title', 'Business Settings')
@section('subtitle', 'Manage organization parameters, default profit margins, and localization')

@section('content')
<style>
.settings-tab-btn{display:flex;align-items:center;justify-content:space-between;width:100%;padding:11px 16px;font-size:13px;font-weight:600;color:#475569;border:1px solid #e2e8f0;border-bottom:0;background:#ffffff;text-align:left;transition:all .15s ease-in-out}
.settings-tab-btn:first-child{border-top-left-radius:12px;border-top-right-radius:12px}
.settings-tab-btn:last-child{border-bottom:1px solid #e2e8f0;border-bottom-left-radius:12px;border-bottom-right-radius:12px}
.settings-tab-btn:hover{background:#f5f3ff;color:#7c3aed}
.settings-tab-btn.active{background:#7c3aed!important;color:#ffffff!important;font-weight:700;border-color:#7c3aed!important;position:relative}
.settings-tab-btn.active::after{content:'';position:absolute;right:-10px;top:50%;transform:translateY(-50%);border-width:6px 0 6px 10px;border-style:solid;border-color:transparent transparent transparent #7c3aed}

/* Allow overflow visible so dropdown popovers & datepickers don't get clipped */
.settings-card{background:#ffffff;border:1px solid #f3e8ff;border-radius:16px;box-shadow:0 4px 20px -2px rgba(124,58,237,0.05);overflow:visible !important}
.tab-panel{overflow:visible !important}
.settings-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
@media(max-width:1024px){.settings-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.settings-form-grid{grid-template-columns:minmax(0,1fr)}}
.settings-form-grid label{display:block;font-size:13px;font-weight:650;color:#1e293b;margin-bottom:6px}
.settings-form-grid input:not([type=checkbox]):not([type=file]),.settings-form-grid select{width:100%;font-size:13px;height:42px;padding:8px 12px;border:1px solid #cbd5e1;border-radius:10px;transition:border-color .15s,box-shadow .15s;background-color:#ffffff}
.settings-form-grid input:focus,.settings-form-grid select:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,0.15)}

/* Icon Input Group Box styling matching reference photo */
.settings-input-group {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}
.settings-input-group .input-icon-box {
    position: absolute;
    left: 1px;
    top: 1px;
    bottom: 1px;
    width: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-right: 1px solid #cbd5e1;
    border-top-left-radius: 9px;
    border-bottom-left-radius: 9px;
    color: #64748b;
    font-size: 14px;
    z-index: 5;
}
.settings-input-group input,
.settings-input-group select,
.settings-input-group .custom-select-trigger {
    padding-left: 48px !important;
}

/* Custom Searchable Dropdown Styling */
.custom-select-wrapper {
    position: relative;
    width: 100%;
}
.custom-select-trigger {
    width: 100%;
    height: 42px;
    padding: 8px 32px 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    font-size: 13px;
    color: #1e293b;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
    transition: border-color .15s, box-shadow .15s;
}
.custom-select-trigger:focus,
.custom-select-wrapper.open .custom-select-trigger {
    outline: none;
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
}
.custom-select-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    z-index: 500;
    display: none;
    overflow: hidden;
}
.custom-select-wrapper.open .custom-select-dropdown {
    display: block;
}
.custom-select-search {
    padding: 8px;
    border-bottom: 1px solid #f1f5f9;
    background: #f8fafc;
}
.custom-select-search input {
    width: 100% !important;
    height: 34px !important;
    padding: 6px 10px !important;
    font-size: 12px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    background: #ffffff !important;
}
.custom-select-search input:focus {
    border-color: #7c3aed !important;
    box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.15) !important;
}
.custom-select-options {
    max-height: 220px;
    overflow-y: auto;
    padding: 4px 0;
}
.custom-select-options::-webkit-scrollbar {
    width: 6px;
}
.custom-select-options::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-select-options::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
.custom-select-option {
    padding: 9px 14px;
    font-size: 13px;
    color: #334155;
    cursor: pointer;
    transition: background .12s, color .12s;
}
.custom-select-option:hover {
    background: #f1f5f9;
    color: #7c3aed;
}
.custom-select-option.selected {
    background: #7c3aed;
    color: #ffffff;
    font-weight: 600;
}
.custom-select-option.hidden {
    display: none;
}
.custom-select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #64748b;
    font-size: 12px;
    transition: transform .15s ease;
}
.custom-select-wrapper.open .custom-select-arrow {
    transform: translateY(-50%) rotate(180deg);
}

/* Datepicker Popup Styling (Matching Photo 1) */
.datepicker-popup {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    width: 270px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.15);
    z-index: 600;
    padding: 12px;
    display: none;
    user-select: none;
}
.datepicker-popup.open {
    display: block;
}
.datepicker-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 700;
    font-size: 13px;
    color: #1e293b;
}
.datepicker-nav-btn {
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    cursor: pointer;
    color: #64748b;
    font-size: 14px;
    transition: background 0.15s, color 0.15s;
}
.datepicker-nav-btn:hover {
    background: #f1f5f9;
    color: #7c3aed;
}
.datepicker-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    text-align: center;
}
.datepicker-day-header {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    padding-bottom: 4px;
}
.datepicker-day-cell {
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    border-radius: 6px;
    cursor: pointer;
    color: #334155;
    transition: all 0.15s ease;
}
.datepicker-day-cell:hover {
    background: #f1f5f9;
}
.datepicker-day-cell.other-month {
    color: #cbd5e1;
}
.datepicker-day-cell.selected {
    background: #0284c7;
    color: #ffffff;
    font-weight: 700;
}

/* Tooltip Popover styling for info badges */
.settings-info-wrap {
    position: relative;
    display: inline-block;
}
.settings-info {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    margin-left: 4px;
    border-radius: 50%;
    background: #7c3aed;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    vertical-align: middle;
    line-height: 1;
}
.product-settings-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px 28px}
.product-settings-field label{display:block;margin-bottom:7px;font-size:13px;font-weight:650;color:#1e293b}
.product-settings-field input:not([type=checkbox]),.product-settings-field select{width:100%;height:42px;padding:8px 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-size:13px}
.product-settings-field input:focus,.product-settings-field select:focus{outline:none;border-color:#7c3aed;box-shadow:0 0 0 3px #ede9fe}
.product-settings-check{display:flex;align-items:center;gap:9px;min-height:34px;font-size:13px;color:#334155;cursor:pointer}
.product-settings-check input{width:17px;height:17px;accent-color:#7c3aed;flex:none}
.product-settings-check .settings-info-wrap{flex:none}
.product-settings-expiry{display:flex;align-items:center;gap:8px}
.product-settings-expiry select{min-width:0}
.product-settings-expiry input[type=checkbox]{width:17px;height:17px;accent-color:#7c3aed;flex:none}
.product-settings-expiry input[type=number]{max-width:78px}
.product-settings-expiry select:disabled{background:#f1f5f9;color:#64748b;cursor:not-allowed;opacity:1}
.product-settings-expiry select:disabled:hover{cursor:not-allowed}
.product-settings-expiry input[type=number]:disabled{background:#f1f5f9;color:#64748b;cursor:not-allowed;opacity:1}
@media(max-width:900px){.product-settings-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.product-settings-grid{grid-template-columns:minmax(0,1fr)}}
.settings-tooltip-card {
    position: absolute;
    top: 100%;
    right: 0;
    width: 270px;
    margin-top: 8px;
    padding: 12px 14px;
    background: #ffffff;
    color: #475569;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.5;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.12), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease, transform 0.2s ease;
    transform: translateY(-4px);
    pointer-events: none;
    text-align: left;
}
.settings-tooltip-card.tooltip-left {
    left: 0;
    right: auto;
}
.settings-tooltip-card::before {
    content: '';
    position: absolute;
    bottom: 100%;
    right: 10px;
    border-width: 0 6px 7px 6px;
    border-style: solid;
    border-color: transparent transparent #ffffff transparent;
    z-index: 1001;
}
.settings-tooltip-card.tooltip-left::before {
    left: 10px;
    right: auto;
}
.settings-tooltip-card::after {
    content: '';
    position: absolute;
    bottom: 100%;
    right: 9px;
    border-width: 0 7px 8px 7px;
    border-style: solid;
    border-color: transparent transparent #cbd5e1 transparent;
    z-index: 1000;
}
.settings-tooltip-card.tooltip-left::after {
    left: 9px;
    right: auto;
}
.settings-info-wrap:hover .settings-tooltip-card,
.settings-info:focus + .settings-tooltip-card {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
</style>

@if(session('status') || session('success'))
<div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold flex items-center gap-2" role="alert">
    <i class="bi bi-check-circle-fill text-lg text-emerald-600"></i>
    <span>{{ session('status') ?? session('success') }}</span>
</div>
@endif

<form id="business-settings-form" method="POST" action="{{ route('business.settings.update') }}" enctype="multipart/form-data">
    @csrf

    <div class="grid grid-cols-12 gap-6 items-start">

        <!-- Left Vertical Navigation Tabs -->
        <div class="col-span-12 lg:col-span-3">
            <div class="rounded-xl overflow-hidden shadow-xs">
                @php
                $tabs = [
                    'business' => ['label' => 'Business', 'icon' => ''],
                    'tax' => ['label' => 'Tax', 'info' => true],
                    'product' => ['label' => 'Product'],
                    'contact' => ['label' => 'Contact'],
                    'sale' => ['label' => 'Sale'],
                    'pos' => ['label' => 'POS'],
                    'purchases' => ['label' => 'Purchases'],
                    'payment' => ['label' => 'Payment'],
                    'dashboard' => ['label' => 'Dashboard'],
                    'system' => ['label' => 'System'],
                    'prefixes' => ['label' => 'Prefixes'],
                    'email' => ['label' => 'Email Settings'],
                    'sms' => ['label' => 'SMS Settings'],
                    'reward' => ['label' => 'Reward Point Settings'],
                    'modules' => ['label' => 'Modules'],
                    'custom-labels' => ['label' => 'Custom Labels'],
                ];
                @endphp
                @foreach($tabs as $key => $tab)
                <button type="button" class="settings-tab-btn {{ $key === 'business' ? 'active' : '' }}" data-tab-target="tab-{{ $key }}">
                    <span>
                        {{ $tab['label'] }}
                        @if(isset($tab['info']))
                        <span class="settings-info-wrap">
                            <span class="settings-info" tabindex="0">i</span>
                            <div class="settings-tooltip-card tooltip-left">
                                <strong class="text-slate-700 block mb-1">Tax Settings</strong>
                                <p class="text-slate-500">Tax settings for your business. Configure tax rates and calculation policies.</p>
                            </div>
                        </span>
                        @endif
                    </span>
                </button>
                @endforeach
            </div>
        </div>

        <!-- Right Content Panel -->
        <div class="col-span-12 lg:col-span-9">
            <div class="settings-card p-6">

                <!-- TAB 1: BUSINESS (ACTIVE) -->
                <div id="tab-business" class="tab-panel">
                    <div class="settings-form-grid">

                        <!-- Row 1 -->
                        <div>
                            <label for="business_name">Business Name:*</label>
                            <input type="text" id="business_name" name="business_name" required value="{{ old('business_name', $settings->business_name) }}" placeholder="Business Name">
                        </div>

                        <div>
                            <label for="start_date">Start Date:</label>
                            <div class="relative">
                                <div class="settings-input-group">
                                    <div class="input-icon-box cursor-pointer" id="start-date-icon-box" title="Open Calendar"><i class="bi bi-calendar-event"></i></div>
                                    <input type="text" id="start_date" name="start_date" class="bg-slate-100/70 cursor-pointer" value="{{ old('start_date', $settings->start_date) }}" placeholder="01/01/2015">
                                </div>
                                <!-- Datepicker Popup Calendar (Photo 1) -->
                                <div id="datepicker-popup" class="datepicker-popup">
                                    <div class="datepicker-header">
                                        <button type="button" id="dp-prev-month" class="datepicker-nav-btn">&laquo;</button>
                                        <span id="dp-month-year-title" class="font-bold text-slate-800 text-sm">January 2015</span>
                                        <button type="button" id="dp-next-month" class="datepicker-nav-btn">&raquo;</button>
                                    </div>
                                    <div class="datepicker-grid">
                                        <div class="datepicker-day-header">Su</div>
                                        <div class="datepicker-day-header">Mo</div>
                                        <div class="datepicker-day-header">Tu</div>
                                        <div class="datepicker-day-header">We</div>
                                        <div class="datepicker-day-header">Th</div>
                                        <div class="datepicker-day-header">Fr</div>
                                        <div class="datepicker-day-header">Sa</div>
                                    </div>
                                    <div id="datepicker-days-grid" class="datepicker-grid mt-1"></div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="default_profit_percent">
                                Default profit percent:*
                                <span class="settings-info-wrap">
                                    <span class="settings-info" tabindex="0">i</span>
                                    <div class="settings-tooltip-card">
                                        <strong class="text-slate-800 block mb-1 font-bold">Default profit margin of a product.</strong>
                                        <p class="text-slate-500 mb-1">Used to calculate selling price based on purchase price entered.</p>
                                        <p class="text-slate-500">You can modify this value for individual products while adding</p>
                                    </div>
                                </span>
                            </label>
                            <div class="settings-input-group">
                                <div class="input-icon-box"><i class="bi bi-plus-circle"></i></div>
                                <input type="number" step="0.01" id="default_profit_percent" name="default_profit_percent" required value="{{ old('default_profit_percent', number_format($settings->default_profit_percent, 2, '.', '')) }}">
                            </div>
                        </div>

                        <!-- Row 2 -->
                        <div>
                            <label for="currency">Currency:</label>
                            <div class="settings-input-group">
                                <div class="input-icon-box"><i class="bi bi-cash-stack"></i></div>
                                <select id="currency" name="currency" required>
                                    @foreach($currencies as $curr)
                                    <option value="{{ $curr }}" @selected(old('currency', $settings->currency) === $curr)>{{ $curr }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="currency_symbol_placement">Currency Symbol Placement:</label>
                            <select id="currency_symbol_placement" name="currency_symbol_placement" required>
                                <option value="Before amount" @selected(old('currency_symbol_placement', $settings->currency_symbol_placement) === 'Before amount')>Before amount</option>
                                <option value="After amount" @selected(old('currency_symbol_placement', $settings->currency_symbol_placement) === 'After amount')>After amount</option>
                            </select>
                        </div>

                        <div>
                            <label for="time_zone">Time zone:</label>
                            <div class="settings-input-group">
                                <div class="input-icon-box"><i class="bi bi-clock"></i></div>
                                <select id="time_zone" name="time_zone" required>
                                    @foreach($timezones as $tz)
                                    <option value="{{ $tz }}" @selected(old('time_zone', $settings->time_zone) === $tz)>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Row 3 -->
                        <div>
                            <label for="logo">Upload Logo:</label>
                            <div class="flex gap-2">
                                <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                                <input type="text" id="logo-display-name" readonly placeholder="" class="flex-1 bg-slate-50 cursor-pointer">
                                <button type="button" onclick="document.getElementById('logo').click()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-bold text-xs transition-all shrink-0 flex items-center gap-1.5 shadow-md shadow-purple-500/20"><i class="bi bi-folder2-open"></i> Browse..</button>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1 italic">Previous logo (if exists) will be replaced</p>
                            @if($settings->logo_path)
                            <div class="mt-2 flex items-center gap-3 p-2 bg-slate-50 rounded-lg border border-slate-200">
                                <img src="{{ $settings->logo_path }}" alt="Business Logo" class="h-10 max-w-[120px] object-contain rounded">
                                <span class="text-xs text-slate-500">Current Logo</span>
                            </div>
                            @endif
                        </div>

                        <div>
                            <label for="financial_year_start_month">
                                Financial year start month:
                                <span class="settings-info-wrap">
                                    <span class="settings-info" tabindex="0">i</span>
                                    <div class="settings-tooltip-card">
                                        <strong class="text-slate-800 block font-normal">Starting month of The Financial Year for your business</strong>
                                    </div>
                                </span>
                            </label>
                            <div class="settings-input-group">
                                <div class="input-icon-box"><i class="bi bi-calendar3"></i></div>
                                <select id="financial_year_start_month" name="financial_year_start_month" required>
                                    @foreach($months as $month)
                                    <option value="{{ $month }}" @selected(old('financial_year_start_month', $settings->financial_year_start_month) === $month)>{{ $month }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="stock_accounting_method">
                                Stock Accounting Method:*
                                <span class="settings-info-wrap">
                                    <span class="settings-info" tabindex="0">i</span>
                                    <div class="settings-tooltip-card">
                                        <span class="text-slate-700 block font-normal">Accounting method</span>
                                    </div>
                                </span>
                            </label>
                            <div class="settings-input-group">
                                <div class="input-icon-box"><i class="bi bi-calculator"></i></div>
                                <select id="stock_accounting_method" name="stock_accounting_method" required>
                                    <option value="FIFO (First In First Out)" @selected(old('stock_accounting_method', $settings->stock_accounting_method) === 'FIFO (First In First Out)')>FIFO (First In First Out)</option>
                                    <option value="LIFO (Last In First Out)" @selected(old('stock_accounting_method', $settings->stock_accounting_method) === 'LIFO (Last In First Out)')>LIFO (Last In First Out)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 4 -->
                        <div>
                            <label for="transaction_edit_days">
                                Transaction Edit Days:*
                                <span class="settings-info-wrap">
                                    <span class="settings-info" tabindex="0">i</span>
                                    <div class="settings-tooltip-card tooltip-left" style="width: 280px;">
                                        <span class="text-slate-700 block font-normal">Number of days from Transaction Date till which a transaction can be edited.</span>
                                    </div>
                                </span>
                            </label>
                            <div class="settings-input-group">
                                <div class="input-icon-box"><i class="bi bi-pencil-square"></i></div>
                                <input type="number" id="transaction_edit_days" name="transaction_edit_days" required min="0" max="36500" value="{{ old('transaction_edit_days', $settings->transaction_edit_days) }}">
                            </div>
                        </div>

                        <div>
                            <label for="date_format">Date Format:*</label>
                            <div class="settings-input-group">
                                <div class="input-icon-box"><i class="bi bi-calendar-check"></i></div>
                                <select id="date_format" name="date_format" required>
                                    <option value="mm/dd/yyyy" @selected(old('date_format', $settings->date_format) === 'mm/dd/yyyy')>mm/dd/yyyy</option>
                                    <option value="dd/mm/yyyy" @selected(old('date_format', $settings->date_format) === 'dd/mm/yyyy')>dd/mm/yyyy</option>
                                    <option value="yyyy-mm-dd" @selected(old('date_format', $settings->date_format) === 'yyyy-mm-dd')>yyyy-mm-dd</option>
                                    <option value="dd-mm-yyyy" @selected(old('date_format', $settings->date_format) === 'dd-mm-yyyy')>dd-mm-yyyy</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="time_format">Time Format:*</label>
                            <div class="settings-input-group">
                                <div class="input-icon-box"><i class="bi bi-clock-history"></i></div>
                                <select id="time_format" name="time_format" required>
                                    <option value="12 Hour" @selected(old('time_format', $settings->time_format) === '12 Hour')>12 Hour</option>
                                    <option value="24 Hour" @selected(old('time_format', $settings->time_format) === '24 Hour')>24 Hour</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 5 -->
                        <div>
                            <label for="currency_precision">
                                Currency precision:*
                                <span class="settings-info-wrap">
                                    <span class="settings-info" tabindex="0">i</span>
                                    <div class="settings-tooltip-card tooltip-left" style="width: 290px;">
                                        <span class="text-slate-700 block font-normal">Number of digits after decimal point for currency value. Example:0.00 for value 2, 0.000 for value 3, 0.0000 for value 4</span>
                                    </div>
                                </span>
                            </label>
                            <select id="currency_precision" name="currency_precision" required>
                                @for($i = 0; $i <= 4; $i++)
                                <option value="{{ $i }}" @selected((int)old('currency_precision', $settings->currency_precision) === $i)>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>

                        <div>
                            <label for="quantity_precision">
                                Quantity precision:*
                                <span class="settings-info-wrap">
                                    <span class="settings-info" tabindex="0">i</span>
                                    <div class="settings-tooltip-card" style="width: 290px;">
                                        <span class="text-slate-700 block font-normal">Number of digits after decimal point for quantity value. Example:0.00 for value 2, 0.000 for value 3, 0.0000 for value 4</span>
                                    </div>
                                </span>
                            </label>
                            <select id="quantity_precision" name="quantity_precision" required>
                                @for($i = 0; $i <= 4; $i++)
                                <option value="{{ $i }}" @selected((int)old('quantity_precision', $settings->quantity_precision) === $i)>{{ $i }}</option>@endfor
                            </select>
                        </div>

                    </div>
                </div>

                @php
                    $productOptions = array_replace([
                        'sku_prefix' => '', 'expiry_enabled' => false, 'expiry_mode' => 'item_expiry',
                        'on_expiry' => 'keep_selling', 'expiry_grace_days' => 0, 'default_unit_id' => null,
                        'enable_brands' => true, 'enable_categories' => true, 'enable_subcategories' => true,
                        'enable_price_tax' => true, 'enable_our_price' => false, 'enable_sub_units' => false,
                        'enable_racks' => false, 'enable_row' => false, 'enable_position' => false,
                        'enable_warranty' => false, 'enable_secondary_unit' => false, 'enable_serial_numbers' => false,
                    ], $settings->other_settings['product'] ?? []);
                    $productOption = fn ($key) => old('product_settings.'.$key, $productOptions[$key]);
                @endphp
                <div id="tab-product" class="tab-panel hidden">
                    <div class="product-settings-grid">
                        <div class="product-settings-field">
                            <label for="sku_prefix">SKU prefix:</label>
                            <input id="sku_prefix" name="product_settings[sku_prefix]" maxlength="30" value="{{ $productOption('sku_prefix') }}" placeholder="e.g. PRD">
                        </div>
                        <div class="product-settings-field">
                            <label for="expiry_enabled">Enable Product Expiry:
                                <span class="settings-info-wrap"><span class="settings-info" tabindex="0" aria-label="Product expiry help">i</span><span class="settings-tooltip-card"><strong>Enable product expiry.</strong><br><br><strong>Add item expiry:</strong> To directly add item expiry only.<br><strong>Add manufacturing date &amp; expiry period:</strong> To add manufacturing date and expiry period and calculate expiry date based on that.</span></span>
                            </label>
                            <div class="product-settings-expiry">
                                <input type="hidden" name="product_settings[expiry_enabled]" value="0">
                                <input id="expiry_enabled" type="checkbox" name="product_settings[expiry_enabled]" value="1" @checked($productOption('expiry_enabled')) aria-label="Enable product expiry">
                                <input type="hidden" name="product_settings[expiry_mode]" value="{{ $productOption('expiry_mode') }}">
                                <select id="expiry_mode" name="product_settings[expiry_mode]" @disabled(! $productOption('expiry_enabled'))>
                                    <option value="item_expiry" @selected($productOption('expiry_mode') === 'item_expiry')>Add item expiry</option>
                                    <option value="manufacturing_period" @selected($productOption('expiry_mode') === 'manufacturing_period')>Add manufacturing date &amp; expiry period</option>
                                </select>
                            </div>
                        </div>
                        <div class="product-settings-field" id="on-expiry-field" @if(! $productOption('expiry_enabled')) hidden @endif>
                            <label for="on_expiry">On Product Expiry:
                                <span class="settings-info-wrap"><span class="settings-info" tabindex="0" aria-label="On product expiry help">i</span><span class="settings-tooltip-card">Specify action that needs to be done on product expiry.<br><br><strong>Keep Selling:</strong> Products will be kept on selling after expiry also.<br><strong>Stop Selling:</strong> Stop selling item n days before expiry.</span></span>
                            </label>
                            <div class="product-settings-expiry">
                                <select id="on_expiry" name="product_settings[on_expiry]">
                                    <option value="keep_selling" @selected($productOption('on_expiry') === 'keep_selling')>Keep Selling</option>
                                    <option value="stop_selling" @selected($productOption('on_expiry') === 'stop_selling')>Stop Selling n days before</option>
                                </select>
                                <input type="hidden" name="product_settings[expiry_grace_days]" value="{{ $productOption('expiry_grace_days') }}">
                                <input id="expiry_days" type="number" name="product_settings[expiry_grace_days]" min="0" max="36500" value="{{ $productOption('expiry_grace_days') }}" aria-label="Days before expiry" @disabled($productOption('on_expiry') !== 'stop_selling')>
                            </div>
                        </div>

                        @foreach(['enable_brands' => 'Enable Brands', 'enable_categories' => 'Enable Categories', 'enable_subcategories' => 'Enable Sub-Categories', 'enable_price_tax' => 'Enable Price & Tax', 'enable_our_price' => 'Enable Our Price Feature'] as $key => $label)
                            <div><input type="hidden" name="product_settings[{{ $key }}]" value="0"><label class="product-settings-check"><input type="checkbox" name="product_settings[{{ $key }}]" value="1" @checked($productOption($key))>{{ $label }}</label></div>
                        @endforeach
                        <div class="product-settings-field">
                            <label for="default_unit_id">Default Unit:</label>
                            <select id="default_unit_id" name="product_settings[default_unit_id]">
                                <option value="">Please Select</option>
                                @foreach($units as $unit)<option value="{{ $unit->id }}" @selected($productOption('default_unit_id') == $unit->id)>{{ $unit->name }} ({{ $unit->short_name }})</option>@endforeach
                            </select>
                        </div>
                        @foreach(['enable_sub_units' => 'Enable Sub Units', 'enable_racks' => 'Enable Racks', 'enable_row' => 'Enable Row', 'enable_position' => 'Enable Position', 'enable_warranty' => 'Enable Warranty', 'enable_secondary_unit' => 'Enable secondary unit', 'enable_serial_numbers' => 'Enable serial numbers manage'] as $key => $label)
                            <div><input type="hidden" name="product_settings[{{ $key }}]" value="0"><label class="product-settings-check"><input type="checkbox" name="product_settings[{{ $key }}]" value="1" @checked($productOption($key))>{{ $label }}
                                @if($key === 'enable_sub_units' || $key === 'enable_racks')<span class="settings-info-wrap"><span class="settings-info" tabindex="0" aria-label="{{ $label }} help">i</span><span class="settings-tooltip-card">{{ $key === 'enable_racks' ? 'Enable this to add rack details of a product for different business locations while adding products.' : 'Based on selected Unit it will show sub units for it. Select the sub-unit applicable. Leave blank if all sub-units are applicable for the product.' }}</span></span>@endif
                            </label></div>
                        @endforeach
                    </div>
                </div>

                <!-- OTHER TABS (UI PLACEHOLDERS FOR FUTURE MODULES) -->
                @foreach(['tax', 'contact', 'sale', 'pos', 'purchases', 'payment', 'dashboard', 'system', 'prefixes', 'email', 'sms', 'reward', 'modules', 'custom-labels'] as $otherTab)
                <div id="tab-{{ $otherTab }}" class="tab-panel hidden">
                    <div class="py-8 text-center">
                        <div class="w-16 h-16 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center mx-auto mb-4 text-2xl">
                            <i class="bi bi-sliders"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 capitalize">{{ str_replace('-', ' ', $otherTab) }} Settings</h3>
                        <p class="text-sm text-slate-500 max-w-md mx-auto mt-2">Configure default options, automated rules, and preferences for {{ str_replace('-', ' ', $otherTab) }}.</p>
                        <div class="mt-6 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500 max-w-lg mx-auto">
                            UI module ready. Save business settings anytime using the button below.
                        </div>
                    </div>
                </div>
                @endforeach

            </div>
        </div>

    </div>

    <!-- Floating / Fixed Update Settings Action Button -->
    <div class="mt-8 flex justify-end">
        <button type="submit" class="px-8 py-3 bg-pink-600 hover:bg-pink-700 text-white font-extrabold rounded-xl shadow-lg shadow-pink-500/25 transition-all duration-150 text-sm flex items-center gap-2">
            <i class="bi bi-check2-circle text-base"></i>
            Update Settings
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const expiryEnabled = document.getElementById('expiry_enabled');
    const expiryMode = document.getElementById('expiry_mode');
    const onExpiryField = document.getElementById('on-expiry-field');
    const onExpiry = document.getElementById('on_expiry');
    const expiryDays = document.getElementById('expiry_days');
    if (expiryEnabled && expiryMode && onExpiryField) {
        const syncExpiryFields = () => {
            expiryMode.disabled = !expiryEnabled.checked;
            onExpiryField.hidden = !expiryEnabled.checked;
            if (expiryDays && onExpiry) expiryDays.disabled = onExpiry.value !== 'stop_selling';
        };
        expiryEnabled.addEventListener('change', syncExpiryFields);
        onExpiry?.addEventListener('change', syncExpiryFields);
        syncExpiryFields();
    }

    // Tab switching
    const tabButtons = document.querySelectorAll('.settings-tab-btn');
    const tabPanels = document.querySelectorAll('.tab-panel');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.tabTarget;
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            tabPanels.forEach(panel => {
                panel.classList.toggle('hidden', panel.id !== targetId);
            });
        });
    });

    // Logo file name display
    const logoInput = document.getElementById('logo');
    const logoDisplay = document.getElementById('logo-display-name');
    if (logoInput && logoDisplay) {
        logoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                logoDisplay.value = this.files[0].name;
            }
        });
    }

    // Custom Searchable Select Dropdowns (Capping Dropdown Height & Adding Scrollbar)
    const selectElements = document.querySelectorAll('.settings-form-grid select');
    selectElements.forEach(select => {
        if (select.dataset.customSelectInit) return;
        select.dataset.customSelectInit = 'true';

        // Hide native select visually
        select.style.display = 'none';

        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select-wrapper';

        // Trigger element
        const trigger = document.createElement('div');
        trigger.className = 'custom-select-trigger';
        trigger.tabIndex = 0;

        const selectedOption = select.options[select.selectedIndex] || select.options[0];
        const triggerText = document.createElement('span');
        triggerText.className = 'truncate pr-2';
        triggerText.textContent = selectedOption ? selectedOption.text : '';

        const arrow = document.createElement('i');
        arrow.className = 'bi bi-chevron-down custom-select-arrow';

        trigger.appendChild(triggerText);
        trigger.appendChild(arrow);
        wrapper.appendChild(trigger);

        // Dropdown menu panel
        const dropdown = document.createElement('div');
        dropdown.className = 'custom-select-dropdown';

        // Add search input if options count > 5
        let searchInput = null;
        if (select.options.length > 5) {
            const searchContainer = document.createElement('div');
            searchContainer.className = 'custom-select-search';
            searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.placeholder = 'Search...';
            searchContainer.appendChild(searchInput);
            dropdown.appendChild(searchContainer);
        }

        // Scrollable Options list (max-height: 220px)
        const optionsList = document.createElement('div');
        optionsList.className = 'custom-select-options';

        Array.from(select.options).forEach((opt) => {
            const optEl = document.createElement('div');
            optEl.className = 'custom-select-option' + (opt.selected ? ' selected' : '');
            optEl.textContent = opt.text;
            optEl.dataset.value = opt.value;

            optEl.addEventListener('click', (e) => {
                e.stopPropagation();
                select.value = opt.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));

                triggerText.textContent = opt.text;
                optionsList.querySelectorAll('.custom-select-option').forEach(o => o.classList.remove('selected'));
                optEl.classList.add('selected');
                wrapper.classList.remove('open');
            });

            optionsList.appendChild(optEl);
        });

        dropdown.appendChild(optionsList);
        wrapper.appendChild(dropdown);

        // Insert wrapper into parent
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select); // keep native select inside for form submits

        // Toggle dropdown open/close
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            if (window.closeDatepickerPopup) window.closeDatepickerPopup();
            document.querySelectorAll('.custom-select-wrapper.open').forEach(w => {
                if (w !== wrapper) w.classList.remove('open');
            });
            const isOpen = wrapper.classList.toggle('open');
            if (isOpen && searchInput) {
                setTimeout(() => searchInput.focus(), 50);
            }
        });

        // Search filtering logic
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                optionsList.querySelectorAll('.custom-select-option').forEach(optEl => {
                    const text = optEl.textContent.toLowerCase();
                    optEl.classList.toggle('hidden', term !== '' && !text.includes(term));
                });
            });
            searchInput.addEventListener('click', (e) => e.stopPropagation());
        }
    });

    // Datepicker Popup Logic for Start Date (Photo 1)
    const startDateInput = document.getElementById('start_date');
    const startDateIconBox = document.getElementById('start-date-icon-box');
    const datepickerPopup = document.getElementById('datepicker-popup');
    const dpTitle = document.getElementById('dp-month-year-title');
    const dpDaysGrid = document.getElementById('datepicker-days-grid');
    const dpPrevBtn = document.getElementById('dp-prev-month');
    const dpNextBtn = document.getElementById('dp-next-month');

    if (startDateInput && datepickerPopup) {
        window.closeDatepickerPopup = function() {
            datepickerPopup.classList.remove('open');
        };

        function parseInputDate(str) {
            if (!str) return new Date(2015, 0, 1);
            const parts = str.split(/[\/\-]/);
            if (parts.length === 3) {
                if (parts[0].length === 4) {
                    return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                } else if (parts[2].length === 4) {
                    return new Date(parseInt(parts[2]), parseInt(parts[0]) - 1, parseInt(parts[1]));
                }
            }
            return new Date(2015, 0, 1);
        }

        const initialDate = parseInputDate(startDateInput.value);
        let viewYear = initialDate.getFullYear();
        let viewMonth = initialDate.getMonth();

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        function renderCalendar() {
            dpTitle.textContent = `${monthNames[viewMonth]} ${viewYear}`;
            dpDaysGrid.innerHTML = '';

            const firstDayOfMonth = new Date(viewYear, viewMonth, 1).getDay();
            const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

            // Prev month padding days
            for (let i = firstDayOfMonth - 1; i >= 0; i--) {
                const dayCell = document.createElement('div');
                dayCell.className = 'datepicker-day-cell other-month';
                dayCell.textContent = daysInPrevMonth - i;
                dpDaysGrid.appendChild(dayCell);
            }

            // Current month days
            const curDateObj = parseInputDate(startDateInput.value);
            for (let day = 1; day <= daysInMonth; day++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'datepicker-day-cell';
                dayCell.textContent = day;

                if (curDateObj.getFullYear() === viewYear && curDateObj.getMonth() === viewMonth && curDateObj.getDate() === day) {
                    dayCell.classList.add('selected');
                }

                dayCell.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const mStr = String(viewMonth + 1).padStart(2, '0');
                    const dStr = String(day).padStart(2, '0');
                    let formatted = `${mStr}/${dStr}/${viewYear}`;
                    if (startDateInput.value && startDateInput.value.includes('-') && !startDateInput.value.includes('/')) {
                        formatted = `${viewYear}-${mStr}-${dStr}`;
                    }
                    startDateInput.value = formatted;
                    datepickerPopup.classList.remove('open');
                });

                dpDaysGrid.appendChild(dayCell);
            }

            // Next month padding days to make complete grid
            const totalCells = firstDayOfMonth + daysInMonth;
            const nextDays = (totalCells % 7 === 0) ? 0 : 7 - (totalCells % 7);
            for (let day = 1; day <= nextDays; day++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'datepicker-day-cell other-month';
                dayCell.textContent = day;
                dpDaysGrid.appendChild(dayCell);
            }
        }

        function toggleDatepicker(e) {
            e.stopPropagation();
            document.querySelectorAll('.custom-select-wrapper.open').forEach(w => w.classList.remove('open'));
            const isOpen = datepickerPopup.classList.contains('open');
            if (isOpen) {
                datepickerPopup.classList.remove('open');
            } else {
                renderCalendar();
                datepickerPopup.classList.add('open');
            }
        }

        if (startDateIconBox) startDateIconBox.addEventListener('click', toggleDatepicker);
        if (startDateInput) startDateInput.addEventListener('click', toggleDatepicker);

        dpPrevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            viewMonth--;
            if (viewMonth < 0) {
                viewMonth = 11;
                viewYear--;
            }
            renderCalendar();
        });

        dpNextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            viewMonth++;
            if (viewMonth > 11) {
                viewMonth = 0;
                viewYear++;
            }
            renderCalendar();
        });

        datepickerPopup.addEventListener('click', (e) => e.stopPropagation());
    }

    // Close open dropdowns & datepicker on outside click
    document.addEventListener('click', () => {
        document.querySelectorAll('.custom-select-wrapper.open').forEach(w => w.classList.remove('open'));
        if (window.closeDatepickerPopup) window.closeDatepickerPopup();
    });
});
</script>
@endsection
