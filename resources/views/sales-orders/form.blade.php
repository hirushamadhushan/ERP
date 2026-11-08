@extends('layouts.app')
@section('title', 'Orders')

@section('content')
<div class="max-w-[1700px] mx-auto pb-12">

    <!-- Top Breadcrumb -->
    <div class="flex items-center justify-between mb-5 bg-white border border-purple-100/80 rounded-2xl px-6 py-3.5 shadow-2xs">
        <h1 class="text-base font-extrabold text-slate-800 tracking-tight">Orders</h1>
        <div class="flex items-center gap-2 text-xs font-semibold text-slate-400">
            <span>Sales</span>
            <span class="text-slate-300">&rsaquo;</span>
            <span class="text-slate-600 font-bold">Orders</span>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-5 rounded-2xl bg-rose-50 border border-rose-200 p-4 text-xs font-semibold text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="mb-5 rounded-2xl bg-rose-50 border border-rose-200 p-4 text-xs font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('sales.store') }}" id="order-form">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- ===================== LEFT / MAIN ORDER FORM ===================== -->
            <div class="lg:col-span-9 bg-white border border-purple-100/80 rounded-3xl p-6 sm:p-8 shadow-xs">

                <!-- Editable Title -->
                <div class="flex items-center justify-center gap-2 mb-6">
                    <input type="text" name="title" value="{{ old('title', 'Order') }}" id="order-title-input"
                           class="text-xl font-serif text-slate-800 text-center border-b border-transparent hover:border-slate-300 focus:border-indigo-500 focus:outline-none transition-all px-2 py-0.5"
                           placeholder="Order Title">
                    <i class="bi bi-pencil text-indigo-500 text-xs cursor-pointer" onclick="document.getElementById('order-title-input').focus()"></i>
                </div>

                <!-- Order Number & Primary Details Grid -->
                <div class="mb-8 space-y-4">

                    <!-- Order Number -->
                    <div class="flex items-center gap-4 mb-2">
                        <span class="text-base font-bold text-slate-700">Order No # :</span>
                        <span class="text-lg font-black text-slate-900 tracking-wider font-mono">{{ $nextOrderNumber }}</span>
                        <input type="hidden" name="order_number" value="{{ $nextOrderNumber }}">
                    </div>

                    <!-- Row 1: Customer & Order Date -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-xs font-bold text-slate-700">Customer</span>
                            <select name="customer_id" id="customer-select" required
                                    class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 shadow-2xs transition">
                                <option value="">Select Customer</option>
                                @foreach($customersList as $c)
                                    <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-xs font-bold text-slate-700">Order Date</span>
                            <div class="relative flex-1">
                                <input type="date" name="order_date" value="{{ old('order_date', today()->format('Y-m-d')) }}" required
                                       class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 shadow-2xs transition">
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Sales Person & Valid Until -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-xs font-bold text-slate-700">Sales Person</span>
                            <select name="salesperson_id"
                                    class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 shadow-2xs transition">
                                <option value="">Select Sales Person</option>
                                @foreach($salespeople as $sp)
                                    <option value="{{ $sp->id }}" {{ old('salesperson_id') == $sp->id ? 'selected' : '' }}>{{ $sp->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-xs font-bold text-slate-700">Valid Until</span>
                            <div class="relative flex-1">
                                <input type="date" name="valid_until" value="{{ old('valid_until', today()->format('Y-m-d')) }}"
                                       class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 shadow-2xs transition">
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Reference -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div></div>
                        <div class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-xs font-bold text-slate-700">Reference</span>
                            <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Reference"
                                   class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 shadow-2xs transition">
                        </div>
                    </div>

                </div>

                <!-- ===================== ITEMS TABLE ===================== -->
                <div class="mb-4 overflow-x-auto rounded border border-slate-200/80 shadow-2xs" style="min-height: 280px; border-radius: 4px;">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 text-[11px] font-bold text-white" style="background-color: #1e1b4b;">
                                <th class="py-2.5 px-3 w-[34%] text-white font-bold">Details</th>
                                <th class="py-2.5 px-3 text-right w-[12%] text-white font-bold">Sales Price</th>
                                <th class="py-2.5 px-3 text-right w-[11%] text-white font-bold">Available Qty</th>
                                <th class="py-2.5 px-3 text-right w-[9%] text-white font-bold">Qty</th>
                                <th class="py-2.5 px-3 text-right w-[10%] text-white font-bold">Discount (%)</th>
                                <th class="py-2.5 px-3 text-right w-[11%] text-white font-bold">Discount Amt.</th>
                                <th class="py-2.5 px-3 text-right w-[10%] text-white font-bold">Total</th>
                                <th class="py-2.5 px-2 w-[3%] text-white"></th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody" class="divide-y divide-slate-100 bg-white">
                            <!-- Rows inserted dynamically by JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Add Buttons Row -->
                <div class="flex items-center gap-2.5 pb-6 mb-6 border-b border-slate-100">
                    <button type="button" id="btn-add-item"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-bold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition shadow-2xs" style="border-radius: 4px;">
                        <i class="bi bi-plus-lg text-slate-500"></i> Add Item
                    </button>
                    <button type="button" id="btn-add-service"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-bold text-purple-700 bg-purple-50 border border-purple-200 hover:bg-purple-100 transition shadow-2xs" style="border-radius: 4px;">
                        <i class="bi bi-tools text-purple-600"></i> Add Service
                    </button>
                    <button type="button" id="btn-add-text"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-bold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition shadow-2xs" style="border-radius: 4px;">
                        <i class="bi bi-card-text text-slate-500"></i> Add Text
                    </button>
                </div>

                <!-- ===================== BOTTOM SECTION: TERMS & TOTALS ===================== -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start mb-6">

                    <!-- Left: Terms & Conditions + Payment Terms -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Terms & Conditions</label>
                            <div class="relative">
                                <textarea name="terms" rows="2"
                                          class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 p-3 text-xs text-slate-700 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition shadow-2xs resize-none">{{ old('terms', '1') }}</textarea>
                                <i class="bi bi-pencil absolute right-3 bottom-3 text-slate-400 text-xs"></i>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <label class="text-xs font-bold text-slate-700">Payment Terms</label>
                                <i class="bi bi-pencil text-indigo-500 text-xs cursor-pointer"></i>
                            </div>
                            <div class="relative">
                                <textarea name="payment_terms" rows="2"
                                          class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 p-3 text-xs text-slate-700 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition shadow-2xs resize-none">{{ old('payment_terms', 'Payment is due within 30 days from the date of the order.') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Totals Breakdown -->
                    <div class="space-y-3 bg-slate-50/50 rounded-2xl border border-slate-200/80 p-5 shadow-2xs">

                        <!-- Gross Total -->
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-xs font-bold text-slate-700 w-28">Total</span>
                            <input type="text" id="order-gross-total" readonly value="0.00"
                                   class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-right text-xs font-bold text-slate-800 shadow-2xs">
                            <input type="hidden" name="total_amount" id="order-gross-total-raw" value="0">
                        </div>

                        <!-- Discount (%) -->
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-xs font-bold text-slate-700 w-28">Discount (%)</span>
                            <input type="number" name="discount_percentage" id="order-discount-percent" min="0" max="100" step="0.01" value="{{ old('discount_percentage', '0') }}"
                                   class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-right text-xs font-bold text-slate-800 shadow-2xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                        </div>

                        <!-- Discount Amount -->
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-xs font-bold text-slate-700 w-28">Discount</span>
                            <span id="order-discount-display" class="flex-1 text-right text-xs font-bold text-slate-700 px-3 py-1">LKR 0.00</span>
                            <input type="hidden" name="discount_amount" id="order-discount-amount-raw" value="0">
                        </div>

                        <!-- Net Total -->
                        <div class="flex items-center justify-between gap-4 pt-2 border-t border-slate-200">
                            <span class="text-xs font-extrabold text-slate-900 w-28">Net Total</span>
                            <input type="text" id="order-net-total-display" readonly value="LKR 0.00"
                                   class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-right text-sm font-extrabold text-indigo-700 shadow-2xs">
                            <input type="hidden" name="net_total" id="order-net-total-raw" value="0">
                        </div>

                    </div>

                </div>

                <!-- Bottom Action Buttons: Save & Discard -->
                <div class="flex items-center justify-end gap-3 pt-5 border-t border-slate-100">
                    <button type="submit"
                            style="background-color: #24526d; border: 1px solid #24526d; color: #ffffff; border-radius: 4px; min-width: 95px; padding: 7px 22px; font-size: 13px; font-weight: 600; cursor: pointer;"
                            class="hover:opacity-90 transition shadow-2xs">
                        Save
                    </button>
                    <a href="{{ route('sales.index') }}"
                       style="background-color: #ffffff; border: 1px solid #cbd5e1; color: #475569; border-radius: 4px; min-width: 90px; padding: 7px 22px; font-size: 13px; font-weight: 600; text-align: center; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;"
                       class="hover:bg-slate-50 hover:text-slate-700 transition shadow-2xs">
                        Discard
                    </a>
                </div>

            </div>

            <!-- ===================== RIGHT / CUSTOMER DETAILS PANEL ===================== -->
            <div class="lg:col-span-3 bg-white border border-purple-100/80 rounded-3xl p-6 shadow-xs sticky top-20">
                <h2 class="text-sm font-bold text-slate-900 mb-5 border-b border-slate-100 pb-3">Customer Details</h2>

                <div class="space-y-4 text-xs">
                    <div>
                        <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Name</span>
                        <p id="cd-name" class="font-bold text-slate-800 text-sm">—</p>
                    </div>

                    <div>
                        <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Address</span>
                        <p id="cd-address" class="text-slate-600 leading-relaxed">—</p>
                    </div>

                    <div>
                        <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Mobile</span>
                        <p id="cd-mobile" class="text-slate-700 font-medium">—</p>
                    </div>

                    <div>
                        <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Email</span>
                        <p id="cd-email" class="text-slate-700 font-medium truncate">—</p>
                    </div>

                    <div>
                        <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Debit limit</span>
                        <p id="cd-debit-limit" class="text-slate-700 font-bold">LKR 0.00</p>
                    </div>

                    <div>
                        <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Balance</span>
                        <p id="cd-balance" class="text-slate-700 font-bold">LKR 0.00</p>
                    </div>

                    <div>
                        <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Total Due</span>
                        <p id="cd-total-due" class="text-rose-600 font-extrabold text-sm">LKR 0.00</p>
                    </div>

                    <div class="pt-2 border-t border-slate-100">
                        <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Last 5 Invoices</span>
                        <div id="cd-invoices" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                            <span class="text-slate-400 italic text-[11px]">No customer selected</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>

<!-- Row Templates for JS Cloning -->
<template id="item-row-tpl">
    <tr class="item-row group hover:bg-slate-50/50 transition">
        <td class="py-2.5 px-3">
            <select name="lines[IDX][product_id]" class="product-picker w-full rounded border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-purple-600 focus:ring-1 focus:ring-purple-600 shadow-2xs" style="border-radius: 4px;">
                <option value="">Select Item / Product</option>
                @foreach($productsJson as $p)
                    <option value="{{ $p['id'] }}" data-price="{{ $p['price'] }}" data-qty="{{ $p['available_qty'] }}" data-name="{{ $p['name'] }}">
                        {{ $p['name'] }} [{{ $p['code'] }}]
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="lines[IDX][description]" class="line-desc" value="">
            <input type="hidden" name="lines[IDX][item_type]" value="item">
        </td>
        <td class="py-2.5 px-3">
            <input type="number" step="0.01" min="0" name="lines[IDX][unit_price]" class="line-price w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-right text-xs font-semibold text-slate-800 focus:border-purple-600 shadow-2xs" style="border-radius: 4px;" value="0.00">
        </td>
        <td class="py-2.5 px-3">
            <input type="text" readonly name="lines[IDX][available_qty]" class="line-avail w-full rounded border border-slate-100 bg-slate-50 px-2 py-1.5 text-right text-xs font-semibold text-slate-500" style="border-radius: 4px;" value="0">
        </td>
        <td class="py-2.5 px-3">
            <input type="number" step="1" min="1" name="lines[IDX][quantity]" class="line-qty w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-right text-xs font-bold text-slate-800 focus:border-purple-600 shadow-2xs" style="border-radius: 4px;" value="1">
        </td>
        <td class="py-2.5 px-3">
            <input type="number" step="0.1" min="0" max="100" name="lines[IDX][discount_percentage]" class="line-disc-pct w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-right text-xs font-semibold text-slate-800 focus:border-purple-600 shadow-2xs" style="border-radius: 4px;" value="0">
        </td>
        <td class="py-2.5 px-3">
            <input type="number" step="0.01" min="0" name="lines[IDX][discount_amount]" class="line-disc-amt w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-right text-xs font-semibold text-slate-800 focus:border-purple-600 shadow-2xs" style="border-radius: 4px;" value="0.00">
        </td>
        <td class="py-2.5 px-3">
            <input type="text" readonly name="lines[IDX][line_total]" class="line-total w-full rounded border border-slate-100 bg-slate-50 px-2 py-1.5 text-right text-xs font-bold text-slate-900" style="border-radius: 4px;" value="0.00">
        </td>
        <td class="py-2.5 px-2 text-center">
            <button type="button" class="btn-remove-row text-slate-300 hover:text-rose-500 transition" title="Remove row">
                <i class="bi bi-trash text-sm"></i>
            </button>
        </td>
    </tr>
</template>

<template id="service-row-tpl">
    <tr class="item-row group hover:bg-slate-50/50 transition">
        <td class="py-2.5 px-3">
            <select name="lines[IDX][service_id]" class="service-picker w-full rounded border border-purple-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:border-purple-600 focus:ring-1 focus:ring-purple-600 shadow-2xs" style="border-radius: 4px;">
                <option value="">Select Service</option>
                @foreach($servicesJson as $s)
                    <option value="{{ $s['id'] }}" data-price="{{ $s['price'] }}" data-name="{{ $s['name'] }}" data-code="{{ $s['code'] }}">{{ $s['name'] }}@if(!empty($s['code'])) [{{ $s['code'] }}]@endif</option>
                @endforeach
            </select>
            <input type="hidden" name="lines[IDX][description]" class="line-desc" value="">
            <input type="hidden" name="lines[IDX][item_type]" value="service">
        </td>
        <td class="py-2.5 px-3">
            <input type="number" step="0.01" min="0" name="lines[IDX][unit_price]" class="line-price w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-right text-xs font-semibold text-slate-800 focus:border-purple-600 shadow-2xs" style="border-radius: 4px;" value="0.00">
        </td>
        <td class="py-2.5 px-3 text-center text-slate-400 font-semibold">—</td>
        <td class="py-2.5 px-3">
            <input type="number" step="1" min="1" name="lines[IDX][quantity]" class="line-qty w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-right text-xs font-bold text-slate-800 focus:border-purple-600 shadow-2xs" style="border-radius: 4px;" value="1">
        </td>
        <td class="py-2.5 px-3">
            <input type="number" step="0.1" min="0" max="100" name="lines[IDX][discount_percentage]" class="line-disc-pct w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-right text-xs font-semibold text-slate-800 focus:border-purple-600 shadow-2xs" style="border-radius: 4px;" value="0">
        </td>
        <td class="py-2.5 px-3">
            <input type="number" step="0.01" min="0" name="lines[IDX][discount_amount]" class="line-disc-amt w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-right text-xs font-semibold text-slate-800 focus:border-purple-600 shadow-2xs" style="border-radius: 4px;" value="0.00">
        </td>
        <td class="py-2.5 px-3">
            <input type="text" readonly name="lines[IDX][line_total]" class="line-total w-full rounded border border-slate-100 bg-slate-50 px-2 py-1.5 text-right text-xs font-bold text-slate-900" style="border-radius: 4px;" value="0.00">
        </td>
        <td class="py-2.5 px-2 text-center">
            <button type="button" class="btn-remove-row text-slate-300 hover:text-rose-500 transition" title="Remove row">
                <i class="bi bi-trash text-sm"></i>
            </button>
        </td>
    </tr>
</template>

<template id="text-row-tpl">
    <tr class="item-row group hover:bg-slate-50/50 transition">
        <td colspan="7" class="py-2 px-3">
            <input type="text" name="lines[IDX][description]" placeholder="Notes or instructions line..." class="line-desc w-full rounded border border-slate-200 bg-slate-50/60 px-3 py-1.5 text-xs text-slate-700 italic focus:bg-white focus:border-purple-600 shadow-2xs" style="border-radius: 4px;">
            <input type="hidden" name="lines[IDX][item_type]" value="text">
            <input type="hidden" name="lines[IDX][quantity]" value="1">
            <input type="hidden" name="lines[IDX][unit_price]" value="0">
        </td>
        <td class="py-2 px-2 text-center">
            <button type="button" class="btn-remove-row text-slate-300 hover:text-rose-500 transition" title="Remove row">
                <i class="bi bi-trash text-sm"></i>
            </button>
        </td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
(()=>{
    const customers = @json($customersJson);
    const services = @json($servicesJson ?? []);
    let rowIndex = 0;

    const tbody = document.getElementById('items-tbody');
    const itemTpl = document.getElementById('item-row-tpl');
    const serviceTpl = document.getElementById('service-row-tpl');
    const textTpl = document.getElementById('text-row-tpl');

    // Customer Selection Change Listener -> Updates Right Sidebar
    const customerSelect = document.getElementById('customer-select');
    function updateCustomerDetails(customerId) {
        const c = customers.find(x => x.id == customerId);
        if (!c) {
            document.getElementById('cd-name').textContent = '—';
            document.getElementById('cd-address').textContent = '—';
            document.getElementById('cd-mobile').textContent = '—';
            document.getElementById('cd-email').textContent = '—';
            document.getElementById('cd-debit-limit').textContent = 'LKR 0.00';
            document.getElementById('cd-balance').textContent = 'LKR 0.00';
            document.getElementById('cd-total-due').textContent = 'LKR 0.00';
            document.getElementById('cd-invoices').innerHTML = '<span class="text-slate-400 italic text-[11px]">No customer selected</span>';
            return;
        }

        document.getElementById('cd-name').textContent = c.name + (c.business_name ? ' (' + c.business_name + ')' : '');
        document.getElementById('cd-address').textContent = c.address || '—';
        document.getElementById('cd-mobile').textContent = c.mobile || '—';
        document.getElementById('cd-email').textContent = c.email || '—';
        document.getElementById('cd-debit-limit').textContent = 'LKR ' + c.credit_limit;
        document.getElementById('cd-balance').textContent = 'LKR ' + c.balance;
        document.getElementById('cd-total-due').textContent = 'LKR ' + c.total_due;

        if (c.invoices && c.invoices.length > 0) {
            document.getElementById('cd-invoices').innerHTML = c.invoices.map(inv => `
                <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 border border-slate-100 text-[11px]">
                    <div>
                        <span class="font-bold text-slate-800">${inv.invoice_number}</span>
                        <span class="block text-[10px] text-slate-400">${inv.date}</span>
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-slate-700">LKR ${inv.amount}</span>
                        <span class="block text-[9px] font-semibold text-emerald-600">${inv.status}</span>
                    </div>
                </div>
            `).join('');
        } else {
            document.getElementById('cd-invoices').innerHTML = '<span class="text-slate-400 italic text-[11px]">No previous invoices</span>';
        }
    }

    customerSelect.addEventListener('change', e => updateCustomerDetails(e.target.value));
    if (customerSelect.value) {
        updateCustomerDetails(customerSelect.value);
    }

    // Click-to-empty helper for numeric input fields
    function setupEmptyOnClick(input, defaultVal) {
        if (!input) return;

        function clearIfDefault() {
            const v = input.value.trim();
            if (v === '0' || v === '0.00' || v === '0.0' || v === '0.0000' || parseFloat(v) === 0) {
                input.value = '';
            } else {
                setTimeout(() => input.select(), 0);
            }
        }

        input.addEventListener('focus', clearIfDefault);
        input.addEventListener('click', clearIfDefault);

        input.addEventListener('blur', function() {
            if (input.value.trim() === '') {
                input.value = defaultVal;
                recalculate();
            }
        });
    }

    // Calculations
    function recalculate() {
        let grossTotal = 0;

        tbody.querySelectorAll('tr.item-row').forEach(row => {
            const priceInput = row.querySelector('.line-price');
            const qtyInput = row.querySelector('.line-qty');
            const discAmtInput = row.querySelector('.line-disc-amt');
            const totalDisplay = row.querySelector('.line-total');

            if (!priceInput || !qtyInput) return;

            const price = parseFloat(priceInput.value) || 0;
            const qty = parseFloat(qtyInput.value) || 0;
            const lineSubtotal = price * qty;

            const discAmt = parseFloat(discAmtInput ? discAmtInput.value : 0) || 0;
            const lineTotal = Math.max(0, lineSubtotal - discAmt);
            if (totalDisplay) totalDisplay.value = lineTotal.toFixed(2);

            grossTotal += lineTotal;
        });

        document.getElementById('order-gross-total').value = grossTotal.toFixed(2);
        document.getElementById('order-gross-total-raw').value = grossTotal.toFixed(2);

        const orderDiscPct = parseFloat(document.getElementById('order-discount-percent').value) || 0;
        const orderDiscAmt = (grossTotal * orderDiscPct) / 100;

        document.getElementById('order-discount-display').textContent = 'LKR ' + orderDiscAmt.toFixed(2);
        document.getElementById('order-discount-amount-raw').value = orderDiscAmt.toFixed(2);

        const netTotal = Math.max(0, grossTotal - orderDiscAmt);
        document.getElementById('order-net-total-display').value = 'LKR ' + netTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('order-net-total-raw').value = netTotal.toFixed(2);
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    

    function attachRowListeners(row) {
        const productSelect = row.querySelector('.product-picker');
        const priceInput = row.querySelector('.line-price');
        const availInput = row.querySelector('.line-avail');
        const qtyInput = row.querySelector('.line-qty');
        const discPctInput = row.querySelector('.line-disc-pct');
        const discAmtInput = row.querySelector('.line-disc-amt');

        // Apply click-to-empty behavior to numeric inputs
        setupEmptyOnClick(priceInput, '0.00');
        setupEmptyOnClick(qtyInput, '1');
        setupEmptyOnClick(discPctInput, '0');
        setupEmptyOnClick(discAmtInput, '0.00');

        function getSubtotal() {
            const p = parseFloat(priceInput ? priceInput.value : 0) || 0;
            const q = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
            return p * q;
        }

        function syncDiscountOnSubtotalChange() {
            const subtotal = getSubtotal();
            if (row.dataset.lastDiscMode === 'amt') {
                const amt = parseFloat(discAmtInput ? discAmtInput.value : 0) || 0;
                if (subtotal > 0 && amt > 0) {
                    const pct = (amt / subtotal) * 100;
                    if (discPctInput) discPctInput.value = (Math.round(pct * 100) / 100).toFixed(2).replace(/\.00$/, '');
                } else {
                    if (discPctInput) discPctInput.value = '0';
                }
            } else {
                const pct = parseFloat(discPctInput ? discPctInput.value : 0) || 0;
                if (subtotal > 0 && pct > 0) {
                    const amt = (subtotal * pct) / 100;
                    if (discAmtInput) discAmtInput.value = amt.toFixed(2);
                } else {
                    if (discAmtInput) discAmtInput.value = '0.00';
                }
            }
        }

        // Setup service autocomplete if this is a service row
        

        const serviceSelect = row.querySelector('.service-picker');
        if (serviceSelect) {
            serviceSelect.addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                const descInput = row.querySelector('.line-desc');
                if (!opt || !opt.value) {
                    if (descInput) descInput.value = '';
                    if (priceInput) priceInput.value = '0.00';
                    syncDiscountOnSubtotalChange();
                    recalculate();
                    return;
                }
                const price = opt.dataset.price || '0.00';
                const name = opt.dataset.name || opt.text;

                if (priceInput) priceInput.value = parseFloat(price).toFixed(2);
                if (descInput) descInput.value = name;

                syncDiscountOnSubtotalChange();
                recalculate();
            });
        }

        if (productSelect) {
            productSelect.addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                if (!opt || !opt.value) return;
                const price = opt.dataset.price || '0.00';
                const avail = opt.dataset.qty || '0';
                const name = opt.dataset.name || opt.text;

                if (priceInput) priceInput.value = parseFloat(price).toFixed(2);
                if (availInput) availInput.value = avail;
                const descInput = row.querySelector('.line-desc');
                if (descInput) descInput.value = name;

                syncDiscountOnSubtotalChange();
                recalculate();
            });
        }

        // When Discount Amount is typed -> Auto calculate Discount (%)
        if (discAmtInput) {
            discAmtInput.addEventListener('input', () => {
                row.dataset.lastDiscMode = 'amt';
                const subtotal = getSubtotal();
                const amt = parseFloat(discAmtInput.value) || 0;

                if (subtotal > 0 && amt > 0) {
                    const pct = (amt / subtotal) * 100;
                    if (discPctInput) discPctInput.value = (Math.round(pct * 100) / 100).toFixed(2).replace(/\.00$/, '');
                } else {
                    if (discPctInput) discPctInput.value = '0';
                }
                recalculate();
            });
        }

        // When Discount (%) is typed -> Auto calculate Discount Amount
        if (discPctInput) {
            discPctInput.addEventListener('input', () => {
                row.dataset.lastDiscMode = 'pct';
                const subtotal = getSubtotal();
                const pct = parseFloat(discPctInput.value) || 0;

                if (subtotal > 0 && pct > 0) {
                    const amt = (subtotal * pct) / 100;
                    if (discAmtInput) discAmtInput.value = amt.toFixed(2);
                } else {
                    if (discAmtInput) discAmtInput.value = '0.00';
                }
                recalculate();
            });
        }

        if (priceInput) {
            priceInput.addEventListener('input', () => {
                syncDiscountOnSubtotalChange();
                recalculate();
            });
        }

        if (qtyInput) {
            qtyInput.addEventListener('input', () => {
                syncDiscountOnSubtotalChange();
                recalculate();
            });
        }

        row.querySelector('.btn-remove-row').onclick = () => {
            row.remove();
            recalculate();
        };
    }

    function getRowType(row) {
        return row.querySelector('input[name*="[item_type]"]')?.value || 'item';
    }

    function isRowEmpty(row) {
        if (!row) return false;
        const type = getRowType(row);

        if (type === 'item') {
            const productSelect = row.querySelector('.product-picker');
            const descInput = row.querySelector('.line-desc');
            const priceInput = row.querySelector('.line-price');
            const discAmtInput = row.querySelector('.line-disc-amt');
            const discPctInput = row.querySelector('.line-disc-pct');

            const hasProduct = productSelect && productSelect.value !== '';
            const hasDesc = descInput && descInput.value.trim() !== '';
            const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
            const discAmt = parseFloat(discAmtInput ? discAmtInput.value : 0) || 0;
            const discPct = parseFloat(discPctInput ? discPctInput.value : 0) || 0;

            return !hasProduct && !hasDesc && price === 0 && discAmt === 0 && discPct === 0;
        }

        if (type === 'service') {
            const serviceSelect = row.querySelector('.service-picker');
            const descInput = row.querySelector('.line-desc');
            const priceInput = row.querySelector('.line-price');
            const discAmtInput = row.querySelector('.line-disc-amt');
            const discPctInput = row.querySelector('.line-disc-pct');

            const hasService = serviceSelect && serviceSelect.value !== '';
            const hasDesc = descInput && descInput.value.trim() !== '';
            const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
            const discAmt = parseFloat(discAmtInput ? discAmtInput.value : 0) || 0;
            const discPct = parseFloat(discPctInput ? discPctInput.value : 0) || 0;

            return !hasService && !hasDesc && price === 0 && discAmt === 0 && discPct === 0;
        }

        if (type === 'text') {
            const descInput = row.querySelector('.line-desc');
            return !descInput || descInput.value.trim() === '';
        }

        return false;
    }

    function focusRowInput(row, type) {
        if (!row) return;
        if (type === 'item') {
            const select = row.querySelector('.product-picker');
            if (select) select.focus();
        } else if (type === 'service') {
            const select = row.querySelector('.service-picker');
            if (select) select.focus();
        } else if (type === 'text') {
            const input = row.querySelector('.line-desc');
            if (input) input.focus();
        }
    }

    function createRow(tpl) {
        const clone = tpl.content.cloneNode(true);
        const row = clone.querySelector('tr');
        row.innerHTML = row.innerHTML.replaceAll('IDX', rowIndex++);
        attachRowListeners(row);
        return row;
    }

    function handleRowAdd(type, tpl, shouldFocus = true) {
        const rows = tbody.querySelectorAll('tr.item-row');
        const lastRow = rows.length > 0 ? rows[rows.length - 1] : null;

        // If the last row is completely untouched/empty
        if (lastRow && isRowEmpty(lastRow)) {
            const currentType = getRowType(lastRow);

            // If it's already the same type, keep it and focus
            if (currentType === type) {
                if (shouldFocus) focusRowInput(lastRow, type);
                return;
            }

            // If different type, replace the empty row with the new type
            const newRow = createRow(tpl);
            lastRow.replaceWith(newRow);
            if (shouldFocus) focusRowInput(newRow, type);
            recalculate();
            return;
        }

        // Otherwise append a new row
        const newRow = createRow(tpl);
        tbody.appendChild(newRow);
        if (shouldFocus) focusRowInput(newRow, type);
        recalculate();
    }

    document.getElementById('btn-add-item').onclick = () => handleRowAdd('item', itemTpl, true);
    document.getElementById('btn-add-service').onclick = () => handleRowAdd('service', serviceTpl, true);
    document.getElementById('btn-add-text').onclick = () => handleRowAdd('text', textTpl, true);

    const orderDiscInput = document.getElementById('order-discount-percent');
    if (orderDiscInput) {
        orderDiscInput.addEventListener('input', recalculate);
        setupEmptyOnClick(orderDiscInput, '0');
    }

    // Add first item row by default without auto-focusing
    handleRowAdd('item', itemTpl, false);
})();
</script>
@endpush
