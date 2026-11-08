@extends('layouts.app')
@section('title', $invoice->exists ? $invoice->invoice_number : 'Draft Invoice')
@section('subtitle', 'Customer Invoice')

@section('content')
@php
    $locked = $invoice->status !== 'draft';
    $displayInvoiceNumber = $invoice->invoice_number ?: ($invoiceNumberPreviews[old('location_id', $invoice->location_id) ?: 'default'] ?? $invoiceNumberPreviews['default']);
    $savedLines = old('lines', $invoice->exists ? $invoice->lines->map(fn($line)=>$line->only(['line_type','product_id','product_lot_id','service_id','description','quantity','unit_price','discount_percent','tax_rate_id']))->all() : $presetLines);
    $customerInvoices = \App\Models\SalesInvoice::select('id', 'customer_id', 'invoice_number', 'invoice_date', 'total_amount')
        ->whereIn('customer_id', $customers->pluck('id'))
        ->orderByDesc('invoice_date')
        ->orderByDesc('id')
        ->get()
        ->groupBy('customer_id')
        ->map(fn($invoices) => $invoices->take(5)->map(fn($inv) => [
            'number' => $inv->invoice_number,
            'date' => optional($inv->invoice_date)->format('Y-m-d') ?: '',
            'amount' => number_format((float)$inv->total_amount, 2),
        ]));

    $customerProfiles = $customers->mapWithKeys(function ($customer) use ($customerInvoices) {
        $address = collect([$customer->address_line_1, $customer->address_line_2, $customer->city, $customer->state, $customer->country, $customer->zip_code])->filter()->implode(', ');
        $isVat = !empty(trim($customer->tax_number ?? ''));
        return [(string) $customer->id => [
            'name' => $customer->name,
            'business' => $customer->business_name,
            'contact_id' => $customer->contact_id,
            'address' => $address ?: 'N/A',
            'delivery' => $customer->shipping_address ?: $address,
            'mobile' => $customer->mobile ?: 'N/A',
            'email' => $customer->email ?: 'N/A',
            'tax_number' => $customer->tax_number,
            'is_vat' => $isVat,
            'credit_limit' => number_format((float)($customer->credit_limit ?? 0), 2),
            'advance_balance' => number_format((float)($customer->advance_balance ?? 0), 2),
            'due_balance' => number_format((float)($customer->due_balance ?? 0), 2),
            'last_invoices' => $customerInvoices->get($customer->id, collect())->values(),
        ]];
    });
@endphp
<div class="odoo-invoice-shell" @if($locked) data-invoice-locked @endif>
    <div class="invoice-breadcrumb"><span>Invoices</span><span>Sales <b>›</b> Invoices</span></div>
    @if(session('success') || $errors->any())
        @php
            $invoiceToastSuccess = session('success') !== null;
            $invoiceToastMessage = $invoiceToastSuccess ? session('success') : $errors->first();
            $invoiceToastExtra = $invoiceToastSuccess ? 0 : max(0, $errors->count() - 1);
        @endphp
        <div id="invoice-toast" class="invoice-toast {{ $invoiceToastSuccess ? 'is-success' : 'is-error' }}" role="{{ $invoiceToastSuccess ? 'status' : 'alert' }}" aria-live="polite">
            <i class="bi {{ $invoiceToastSuccess ? 'bi-check-circle-fill' : 'bi-shield-exclamation' }}" aria-hidden="true"></i>
            <p>{{ $invoiceToastMessage }}@if($invoiceToastExtra)<small>+ {{ $invoiceToastExtra }} more {{ Str::plural('issue', $invoiceToastExtra) }}</small>@endif</p>
            <button type="button" aria-label="Close notification"><i class="bi bi-x-lg"></i></button>
        </div>
    @endif

    <div class="invoice-workspace" id="invoice-workspace"><div class="invoice-main">
    <div class="odoo-actionbar" style="border:1px solid #ede9fe;border-radius:16px 16px 0 0">
        @if(!$locked && !$invoice->exists)<button form="invoice-form" class="odoo-confirm" type="submit" name="intent" value="confirm">Confirm</button>@endif
        @if($invoice->exists && !$locked)<button type="submit" form="post-invoice-form" class="odoo-confirm">Confirm</button>@endif
        @if(!$locked)<a class="odoo-cancel" href="{{ route('invoices.index') }}">Cancel</a>@endif
        @if(!$locked && !$invoice->exists)<details class="relative"><summary class="invoice-action primary list-none cursor-pointer" aria-label="Invoice actions"><i class="bi bi-plus-lg"></i> Action</summary><div class="absolute left-0 top-full z-20 mt-2 min-w-40 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl"><button type="button" id="open-orders" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-bold text-slate-700 hover:bg-purple-50 hover:text-purple-700"><i class="bi bi-cart-check"></i> Orders</button></div></details>@endif
        @if($invoice->exists && $locked)<button type="button" class="invoice-action primary" id="open-send"><i class="bi bi-send"></i> Send</button><a class="invoice-action" href="{{ route('invoices.print',$invoice) }}" target="_blank"><i class="bi bi-printer"></i> Print</a>@if($invoice->balance_due > 0)<button type="button" class="invoice-action" id="open-payment"><i class="bi bi-credit-card"></i> Pay</button>@endif<button type="button" class="invoice-action" id="open-preview"><i class="bi bi-eye"></i> Preview</button>@if((float)$invoice->credited_amount < (float)$invoice->total_amount)<button type="button" class="invoice-action" id="open-credit-note"><i class="bi bi-arrow-return-left"></i> Credit Note</button>@endif @if(!$invoice->payments->count() && !$invoice->creditAllocations->count() && !$invoice->creditNotes->count())<button type="submit" form="reset-invoice-form" class="invoice-action"><i class="bi bi-arrow-counterclockwise"></i> Reset to Draft</button>@endif @if($invoice->payments->count())<span class="invoice-payment-count"><i class="bi bi-cash-stack"></i> Payments {{ $invoice->payments->count() }}</span>@endif @endif
        <div class="odoo-status"><span class="{{ $invoice->status==='draft' ? 'active' : '' }}">Draft</span><span class="{{ in_array($invoice->status,['posted','partial']) ? 'active' : '' }}">Posted</span>@if($invoice->status==='partial')<span class="active warning">Partial</span>@endif@if($invoice->status==='paid')<span class="active paid">Paid</span>@endif@if($invoice->status==='credited')<span class="active warning">Credited</span>@endif</div>
    </div>

    @if($invoice->exists && $locked && $invoice->balance_due > 0 && (float)($invoice->customer?->advance_balance) > 0)<div class="invoice-credit-notice"><i class="bi bi-info-circle"></i> You have outstanding credits listed below for this customer.</div>@endif
    <form id="invoice-form" method="POST" action="{{ $invoice->exists ? route('invoices.update',$invoice) : route('invoices.store') }}">
        @csrf @if($invoice->exists) @method('PUT') @endif
        @if($invoice->sales_order_id)<input type="hidden" name="sales_order_id" value="{{ $invoice->sales_order_id }}">@endif
        <section class="odoo-sheet">
            <div class="reference-invoice-heading">
                <div class="ref-heading-left">
                    <span class="ref-heading-label">Invoice No:</span>
                    <strong id="invoice-number-display" @if(!$invoice->exists) title="Next available number; finalized when saved." @endif>{{ $displayInvoiceNumber }}</strong>
                </div>
                <div class="ref-heading-right">
                    @if(!$locked && !$invoice->exists)
                        <button type="button" id="reference-orders" class="reference-orders-btn">ORDERS <i class="bi bi-cart-check"></i></button>
                    @endif
                    @if($invoice->sales_order_id && $invoice->salesOrder)
                        <div class="ref-order-selected-badge" id="ref-order-selected-badge">
                            Order No : <a href="{{ route('sales.index') }}" class="ref-order-link" title="Sales Orders"><b>{{ $invoice->salesOrder->order_number }}</b></a> selected
                        </div>
                    @endif
                </div>
            </div>
            <strong class="odoo-document-type">Customer Invoice</strong>
            <input class="odoo-number" value="{{ $displayInvoiceNumber }}" aria-label="Invoice number" readonly>
            @if($invoice->status==='paid')<span class="invoice-paid-ribbon">PAID</span>@endif
            <div id="payment-terms-toast" class="ref-toast-success hidden">
                <i class="bi bi-check-circle-fill"></i>
                <span>Default payment terms saved successfully!</span>
            </div>

            <div class="odoo-header-grid">
                <!-- Row 1: Customer (Left) & Invoice Date (Right) -->
                <div class="odoo-field invoice-customer-field">
                    <label>Customer</label>
                    <div class="customer-select-col" style="flex:1;display:flex;flex-direction:column;gap:5px;position:relative;">
                        @if($locked)
                            <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
                            <span class="locked-customer-name">{{ $invoice->customer?->name ?: '—' }}</span>
                        @else
                            <div class="reference-customer-dropdown" id="reference-customer-dropdown">
                                <div class="reference-customer-input-box" id="reference-customer-input-box">
                                    <input type="text" id="reference-customer-search" class="reference-customer-search-input" placeholder="Select Customer" autocomplete="off" value="{{ $invoice->customer?->name ?: '' }}">
                                    <button type="button" id="reference-customer-arrow-btn" class="reference-customer-arrow-btn" tabindex="-1"><i class="bi bi-chevron-down"></i></button>
                                </div>
                                <select name="customer_id" id="invoice-customer-id-select" required style="display:none;">
                                    <option value="">Select customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected(old('customer_id',$invoice->customer_id)==$customer->id)>{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                                <div class="reference-customer-menu hidden" id="reference-customer-menu">
                                    <div class="reference-create-customer-wrap">
                                        <button type="button" id="btn-open-create-customer" class="reference-create-customer-btn"><i class="bi bi-plus-circle"></i><span>+ Create New Customer</span></button>
                                    </div>
                                    <div class="reference-customer-list" id="reference-customer-list">
                                        @foreach($customers as $customer)
                                            @php $cAddress = collect([$customer->address_line_1, $customer->city, $customer->state])->filter()->implode(', ') ?: ($customer->city ?: '—'); @endphp
                                            <div class="reference-customer-item @if(old('customer_id', $invoice->customer_id) == $customer->id) is-selected @endif" data-id="{{ $customer->id }}" data-name="{{ $customer->name }}" data-mobile="{{ $customer->mobile }}" data-address="{{ $cAddress }}" data-search="{{ strtolower($customer->name . ' ' . $customer->mobile . ' ' . $cAddress . ' ' . $customer->business_name) }}">
                                                <span class="ref-cust-name">{{ $customer->name }}</span>
                                                <span class="ref-cust-mobile">{{ $customer->mobile ?: '—' }}</span>
                                                <span class="ref-cust-address" title="{{ $cAddress }}">{{ $cAddress }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="reference-customer-empty hidden" id="reference-customer-empty"><i class="bi bi-search"></i><span>No matching customers found</span></div>
                                </div>
                            </div>
                        @endif
                        <div id="customer-vat-badge-container"></div>
                    </div>
                </div>

                <div class="odoo-field reference-field-date">
                    <label>Invoice Date</label>
                    <input name="invoice_date" id="invoice-date-input" type="date" value="{{ old('invoice_date',optional($invoice->invoice_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required>
                </div>

                <!-- Row 2: Inv Type (Left) & Due Date (Right) -->
                <div class="odoo-field reference-field-type">
                    <label>Inv Type</label>
                    <div class="inv-type-trigger-wrap">
                        <button type="button" id="btn-trigger-inv-type" class="inv-type-trigger-btn" title="Change Invoice Type">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            <span id="inv-type-display-value">{{ old('journal', $invoice->journal ?: 'Sales') }}</span>
                        </button>
                        <input type="hidden" name="journal" id="invoice-journal-input" value="{{ old('journal', $invoice->journal ?: 'Sales') }}" required>
                    </div>
                </div>

                <div class="odoo-field reference-field-due">
                    <label>Due Date</label>
                    <input id="invoice-due-date" name="due_date" type="date" value="{{ old('due_date',optional($invoice->due_date)->format('Y-m-d') ?: now()->addDays(30)->format('Y-m-d')) }}">
                </div>

                <!-- Row 3: Reference (Left) & Sales Person (Right) -->
                <div class="odoo-field reference-field-reference">
                    <label>Reference</label>
                    <input type="text" name="customer_reference" id="invoice-customer-reference" placeholder="Reference" value="{{ old('customer_reference', $invoice->customer_reference) }}" class="reference-input-styled">
                </div>

                <div class="odoo-field reference-field-salesperson">
                    <label>Sales Person</label>
                    <select name="salesperson_id" id="invoice-salesperson-id">
                        <option value="">Select Sales Person</option>
                        @foreach($salespeople as $salesperson)
                            <option value="{{ $salesperson->id }}" @selected(old('salesperson_id', $invoice->salesperson_id ?: auth()->id()) == $salesperson->id)>{{ $salesperson->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Row 4: Payment Method (Left) & Stock Type (Right) -->
                <div class="odoo-field reference-field-payment-method">
                    <label>Payment Method</label>
                    <div class="payment-method-select-container">
                        <select name="payment_method" id="invoice-payment-method" class="reference-stock-type-dropdown">
                            <option value="Cash" @selected(old('payment_method', $invoice->payment_method ?: 'Cash') == 'Cash')>Cash</option>
                            <option value="Bank Transfer" @selected(old('payment_method', $invoice->payment_method) == 'Bank Transfer')>Bank Transfer</option>
                            <option value="Card" @selected(old('payment_method', $invoice->payment_method) == 'Card')>Card</option>
                            <option value="Cheque" @selected(old('payment_method', $invoice->payment_method) == 'Cheque')>Cheque</option>
                            <option value="Mobile Wallet" @selected(old('payment_method', $invoice->payment_method) == 'Mobile Wallet')>Mobile Wallet</option>
                        </select>
                    </div>
                </div>

                <div class="odoo-field reference-field-stock-type">
                    <label>Stock Type</label>
                    <div class="stock-type-select-container">
                        <select name="stock_type" id="invoice-stock-type" class="reference-stock-type-dropdown">
                            <option value="Main Stock" @selected(old('stock_type', $invoice->stock_type ?: 'Main Stock') == 'Main Stock')>Main Stock</option>
                            <option value="Vehicle Stock" @selected(old('stock_type', $invoice->stock_type) == 'Vehicle Stock')>Vehicle Stock</option>
                        </select>
                    </div>
                    <input type="hidden" name="location_id" id="invoice-location-id" value="{{ old('location_id', $invoice->location_id ?: ($locations->firstWhere('id', 1)?->id ?? ($locations->first()?->id ?? 1))) }}">
                </div>
            </div>

            <div class="odoo-tabs"><button type="button" class="active" data-tab="lines">Invoice Lines</button><button type="button" data-tab="journal">Journal Items</button><button type="button" data-tab="other">Other Info</button><div class="tax-switch"><button type="button" data-tax="exclusive" class="{{ old('tax_display',$invoice->tax_display ?: 'exclusive')==='exclusive'?'active':'' }}">Tax Excl.</button><button type="button" data-tax="inclusive" class="{{ old('tax_display',$invoice->tax_display)==='inclusive'?'active':'' }}">Tax Incl.</button></div></div>
            <input type="hidden" name="tax_display" id="tax-display" value="{{ old('tax_display',$invoice->tax_display ?: 'exclusive') }}">
            <div class="reference-order-summary {{ ($invoice->sales_order_id || $invoice->salesOrder) ? '' : 'hidden' }}" id="reference-order-summary">
                <label>Order No<input readonly value="{{ $invoice->salesOrder?->order_number ?: ($invoice->sales_order_id ? '000'.$invoice->sales_order_id : '—') }}"></label>
                <label>Order Date<input readonly value="{{ optional($invoice->salesOrder?->order_date)->format('Y-m-d') ?: ($invoice->salesOrder ? date('Y-m-d') : '—') }}"></label>
                <label>Sales Person<input readonly value="{{ $invoice->salesOrder?->salesperson?->name ?: ($invoice->salesperson?->name ?: '—') }}"></label>
            </div>

            <div id="tab-lines" class="odoo-tab-panel">
                <div class="odoo-line-head"><span>Type</span><span>Code</span><span>Details</span><span>Sales Price</span><span>Discount (%)</span><span>Discount Amt.</span><span>Available Qty</span><span>Qty</span><span>Total</span><span>Action</span></div>
                <div id="invoice-lines"></div>
                <div class="odoo-add-row"><button type="button" data-add="product" title="Add an invoice product"><i class="bi bi-plus-circle"></i>Add a line</button><button type="button" data-add="section" title="Group invoice lines under a heading"><i class="bi bi-layout-text-sidebar"></i>Add a section</button><button type="button" data-add="note" title="Add explanatory text"><i class="bi bi-card-text"></i>Add a note</button><button type="button" id="open-catalog" title="Choose products visually"><i class="bi bi-grid"></i>Catalog</button></div>
                <div class="odoo-spacer"></div>

                <!-- Reference Section Matching Photos 2, 3, 4 -->
                <div class="reference-bottom-section">
                    <!-- Left Column: Remark, Terms & Conditions, Payment Terms (Photos 2 & 3) -->
                    <div class="reference-notes-column">
                        <!-- Remark -->
                        <div class="ref-field-card">
                            <label class="ref-card-label" for="invoice-remark">Remark</label>
                            <div class="ref-textarea-container">
                                <textarea name="payment_reference" id="invoice-remark" rows="2" class="ref-custom-textarea" placeholder="Remark">{{ old('payment_reference', $invoice->payment_reference ?: 'You can exchange or return finished goods only within 3 days.') }}</textarea>
                                <span class="ref-textarea-pencil"><i class="bi bi-pencil-square"></i></span>
                            </div>
                        </div>

                        <!-- Terms & Conditions -->
                        <div class="ref-field-card">
                            <label class="ref-card-label" for="invoice-terms">Terms &amp; Conditions</label>
                            <div class="ref-textarea-container">
                                <textarea name="terms" id="invoice-terms" rows="2" class="ref-custom-textarea" placeholder="Terms & Conditions">{{ old('terms', $invoice->terms ?: '1') }}</textarea>
                                <span class="ref-textarea-pencil"><i class="bi bi-pencil-square"></i></span>
                            </div>
                        </div>

                        <!-- Payment Terms Interactive Block (Photos 2 & 3) -->
                        <div class="ref-payment-terms-widget">
                            <div class="ref-pt-header">
                                <span class="ref-pt-title">Payment Terms</span>
                                <button type="button" id="btn-toggle-payment-terms" class="ref-pt-icon-btn" title="Edit default payment terms">
                                    <i class="bi bi-pencil-fill" id="pt-action-icon"></i>
                                </button>
                            </div>
                            <div class="ref-pt-sentence" id="pt-sentence-view">
                                Payment is due within <strong id="pt-days-display">30</strong> days from the date of the invoice.
                            </div>
                            <div class="ref-pt-sentence hidden" id="pt-sentence-edit">
                                Payment is due within <input type="number" id="pt-days-input" class="ref-pt-inline-input" value="30" min="0" max="365"> days from the date of the invoice.
                            </div>
                            <input type="hidden" name="payment_term" id="invoice-payment-term" list="payment-term-options" value="{{ old('payment_term', $invoice->payment_term ?: '30 Days') }}">
                            <datalist id="payment-term-options">@foreach(['Immediate Payment','15 Days','21 Days','30 Days','45 Days','End of Following Month','10 Days after End of Next Month','30% Now, Balance 60 Days'] as $term)<option value="{{ $term }}"></option>@endforeach</datalist>
                        </div>
                    </div>

                    <!-- Right Column: Totals Summary Card & Action Buttons (Photo 4) -->
                    <div class="reference-summary-column">
                        <div class="reference-totals-card">
                            <!-- Total -->
                            <div class="ref-summary-row">
                                <span class="ref-summary-label">Total</span>
                                <input type="text" id="summary-total-display" class="ref-summary-box readonly" readonly value="{{ number_format((float)$invoice->untaxed_amount, 2) }}">
                            </div>

                            <!-- discount / Discount (%) -->
                            <div class="ref-summary-row ref-discount-pct-row">
                                <span class="ref-discount-tag"><i class="bi bi-pencil-square"></i> discount</span>
                                <label for="summary-discount-percent" class="ref-summary-label">Discount (%)</label>
                                <input type="number" name="discount_percent" id="summary-discount-percent" class="ref-summary-box editable" value="{{ old('discount_percent', (float)($invoice->discount_percent ?? 0)) }}" min="0" max="100" step="any">
                            </div>

                            <!-- Discount (Amount) -->
                            <div class="ref-summary-row">
                                <label for="summary-discount-amount" class="ref-summary-label">Discount</label>
                                <input type="number" name="discount_amount" id="summary-discount-amount" class="ref-summary-box editable" value="{{ old('discount_amount', (float)($invoice->discount_amount ?? 0)) }}" min="0" step="any">
                            </div>

                            <!-- Net Total -->
                            <div class="ref-summary-row">
                                <span class="ref-summary-label">Net Total</span>
                                <input type="text" id="summary-net-total-display" class="ref-summary-box readonly highlight" readonly value="{{ number_format((float)$invoice->total_amount, 2) }}">
                            </div>

                            <!-- Tax Inclusive -->
                            <div class="ref-summary-row">
                                <span class="ref-summary-label">Tax Inclusive</span>
                                <input type="text" id="summary-tax-display" class="ref-summary-box readonly" readonly value="{{ number_format((float)$invoice->tax_amount, 2) }}">
                            </div>

                            <!-- Legacy span targets for script compatibility -->
                            <span id="untaxed-total" class="hidden">{{ number_format((float)$invoice->untaxed_amount, 2) }}</span>
                            <span id="tax-total" class="hidden">{{ number_format((float)$invoice->tax_amount, 2) }}</span>
                            <span id="grand-total" class="hidden">{{ number_format((float)$invoice->total_amount, 2) }}</span>
                            <div class="amount-due hidden"><span>Amount Due <sup>?</sup>:</span><b id="amount-due">{{ number_format((float)($invoice->exists ? $invoice->balance_due : $invoice->total_amount), 2) }} Rs</b></div>
                        </div>

                        <!-- Buttons directly underneath totals card (Photo 4) -->
                        <div class="reference-action-bar">
                            <button type="button" id="reference-ledger" class="btn-ref-preview-ledger">
                                <i class="bi bi-eye"></i> Preview Ledger
                            </button>
                            @if(!$locked)
                                <button type="submit" class="btn-ref-main-save" id="btn-save-invoice">
                                    Save
                                </button>
                            @endif
                            <a href="{{ route('invoices.index') }}" class="btn-ref-close-window">
                                Close
                            </a>
                        </div>
                    </div>
                </div>

                @if($locked && $invoice->installments->count()>1)<div class="invoice-installments"><h3>Payment schedule</h3>@foreach($invoice->installments as $installment)<div><span>Installment {{ $installment->sequence }} · {{ $installment->due_date->format('M d, Y') }}</span><b>{{ number_format((float)$installment->amount,2) }} Rs</b><em class="{{ $installment->status }}">{{ ucfirst($installment->status) }}</em></div>@endforeach</div>@endif
                @if($invoice->exists && ($invoice->payments->count() || $invoice->creditAllocations->count()))<div class="invoice-payment-history"><h3>Payments and credits</h3>@foreach($invoice->payments as $payment)<div><span class="payment-ok"><i class="bi bi-check-lg"></i></span><p><b>{{ $payment->payment_number }}</b><small>{{ $payment->payment_date->format('M d, Y') }} · {{ $payment->account->name }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</small></p><strong>{{ number_format($payment->amount,2) }} Rs</strong></div>@endforeach @foreach($invoice->creditAllocations as $credit)<div><span class="payment-ok"><i class="bi bi-arrow-down-left"></i></span><p><b>Customer advance credit</b><small>{{ $credit->created_at->format('M d, Y') }}</small></p><strong>{{ number_format($credit->amount,2) }} Rs</strong></div>@endforeach</div>@endif
                @if($invoice->exists && $locked && $invoice->balance_due > 0 && (float)($invoice->customer?->advance_balance) > 0)<div class="invoice-available-credit"><h3>Outstanding credits</h3><div><button type="submit" form="apply-credit-form">Add</button><span>Customer advance balance</span><strong>{{ number_format(min((float)$invoice->customer->advance_balance,(float)$invoice->balance_due),2) }} Rs</strong></div><small>Available: {{ number_format((float)$invoice->customer->advance_balance,2) }} Rs. Add applies up to the amount due.</small></div>@endif
                @if($invoice->exists && $invoice->creditNotes->count())<div class="invoice-available-credit"><h3>Credit Notes</h3>@foreach($invoice->creditNotes as $note)<div><span><i class="bi bi-arrow-return-left"></i></span><span><a href="{{ route('invoices.credit-notes.show',[$invoice,$note]) }}" target="_blank">{{ $note->credit_number }}</a><small style="display:block">{{ $note->reason }}</small></span><strong>{{ number_format((float)$note->amount,2) }} Rs</strong></div>@endforeach</div>@endif
            </div>
            <div id="tab-journal" class="odoo-tab-panel hidden"><div class="journal-table"><div class="journal-head"><span>Account</span><span>Label</span><span>Analytic Distribution</span><span>Debit</span><span>Credit</span><span>Tax Grids</span></div>@if($locked)@forelse($invoice->journalItems as $item)<div class="journal-item"><span><b>{{ $item->account_code }}</b> {{ $item->account_name }}</span><span>{{ $item->label }}</span><span class="journal-muted">—</span><span>{{ number_format($item->debit,2) }} Rs</span><span>{{ number_format($item->credit,2) }} Rs</span><span class="journal-muted">—</span></div>@empty<div class="journal-empty">No journal entries are recorded for this invoice.</div>@endforelse@else<div class="journal-preview-note">Draft preview · Journal entries are recorded when you confirm the invoice.</div><div id="draft-journal-rows"></div>@endif<div class="journal-total"><span></span><b>Total</b><span></span><strong id="journal-debit-total">{{ $locked ? number_format($invoice->journalItems->sum('debit'),2) : '0.00' }} Rs</strong><strong id="journal-credit-total">{{ $locked ? number_format($invoice->journalItems->sum('credit'),2) : '0.00' }} Rs</strong><span></span></div></div></div>
            <div id="tab-other" class="odoo-tab-panel hidden">
                <div class="odoo-other-grid">
                    <section class="other-info-section">
                        <h3>Invoice</h3>
                        <div class="odoo-field"><label>Recipient Bank <sup class="field-question">?</sup></label><select name="recipient_bank_account_id"><option value="">Select bank account</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}" @selected(old('recipient_bank_account_id',$invoice->recipient_bank_account_id)==$account->id)>{{ $account->name }}</option>@endforeach</select></div>
                        <div class="odoo-field"><label>Delivery Date</label><input name="delivery_date" type="date" value="{{ old('delivery_date',optional($invoice->delivery_date)->format('Y-m-d')) }}"></div>
                    </section>
                    <section class="other-info-section">
                        <h3>Accounting</h3>
                        <div class="odoo-field"><label>Incoterm <sup class="field-question">?</sup></label><input name="incoterm" placeholder="Define a default in the settings" value="{{ old('incoterm',$invoice->incoterm) }}"></div>
                        <div class="odoo-field"><label>Incoterm Location</label><input name="incoterm_location" value="{{ old('incoterm_location',$invoice->incoterm_location) }}"></div>
                        <div class="odoo-field"><label>Fiscal Position <sup class="field-question">?</sup></label><input name="fiscal_position" value="{{ old('fiscal_position',$invoice->fiscal_position) }}"></div>
                        <div class="odoo-field"><label>Payment Method</label><input type="text" id="other-info-payment-method" readonly value="{{ old('payment_method',$invoice->payment_method ?: 'Cash') }}"></div>
                        <div class="odoo-field"><label>Auto-post <sup class="field-question">?</sup></label><select name="auto_post"><option value="0" @selected(!old('auto_post',$invoice->auto_post))>No</option><option value="1" @selected(old('auto_post',$invoice->auto_post))>Yes</option></select></div>
                    </section>
                    <section class="other-info-section other-info-operations">
                        <h3>Operations</h3>
                        @if($commissionAgentEnabled)<div class="odoo-field"><label>Commission Agent</label><select name="commission_agent_id"><option value="">No agent</option>@foreach($agents as $agent)<option value="{{ $agent->id }}" @selected(old('commission_agent_id',$invoice->commission_agent_id)==$agent->id)>{{ $agent->name }}</option>@endforeach</select></div>@endif
                    </section>
                </div>
            </div>
        </section>
    </form>
    @if($invoice->exists && !$locked)<form id="post-invoice-form" method="POST" action="{{ route('invoices.post',$invoice) }}">@csrf</form>@endif
    @if($invoice->exists && $locked)<form id="reset-invoice-form" method="POST" action="{{ route('invoices.reset-draft',$invoice) }}">@csrf</form><form id="apply-credit-form" method="POST" action="{{ route('invoices.credits.apply',$invoice) }}">@csrf</form>@endif
    </div><aside class="invoice-side">
        <section class="reference-customer-card"><h2>Customer Details</h2><div id="reference-customer-details"></div></section>
        <div class="invoice-preview-panel hidden" id="invoice-preview-panel"><div class="invoice-side-heading"><b><i class="bi bi-file-earmark-text"></i> Invoice preview</b><button type="button" id="close-preview" aria-label="Close preview"><i class="bi bi-x-lg"></i></button></div>@if($invoice->exists)<iframe title="Invoice preview" data-src="{{ route('invoices.print',$invoice) }}"></iframe>@endif</div>
        <div class="invoice-communication" id="invoice-communication">
    <div class="odoo-chatter"><button type="button" class="chatter-send" @if(!$invoice->exists || !$locked) disabled @endif>Send message</button><button type="button" class="chatter-note" @if(!$invoice->exists) disabled @endif>Log note</button><button type="button" class="chatter-activity" @if(!$invoice->exists) disabled @endif>Activity</button><span></span><i class="bi bi-person-fill"></i><b>{{ $invoice->exists ? $invoice->activities->count() : 0 }}</b></div>
    <div class="invoice-activity-feed">@if($invoice->exists && $invoice->activities->count())@foreach($invoice->activities as $activity)<article><span><i class="bi {{ $activity->type==='payment'?'bi-cash-coin':($activity->type==='email'?'bi-envelope-check':'bi-check2-circle') }}"></i></span><div><b>{{ $activity->creator?->name ?? 'System' }}</b><small>{{ $activity->created_at->format('M d, Y · H:i') }}</small><p>{{ $activity->message }}</p></div></article>@endforeach @else<p class="invoice-activity-empty">Invoice activity and messages will appear here.</p>@endif</div>
        </div>
    </aside></div>
</div>

@if(!$invoice->exists)
<dialog id="orders-dialog" class="invoice-flow-dialog">
    <header><div><small>Sales</small><h2>Open Order List</h2></div><button type="button" data-close-orders aria-label="Close"><i class="bi bi-x-lg"></i></button></header>
    <div class="overflow-x-auto p-4"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase"><tr><th class="p-3">Order No</th><th>Date</th><th>Customer</th><th>Salesperson</th><th class="text-right">Total</th><th></th></tr></thead><tbody class="divide-y">@forelse($openOrders as $order)<tr><td class="p-3 font-bold">{{ $order->order_number }}</td><td>{{ optional($order->order_date)->format('Y-m-d') }}</td><td>{{ $order->customer?->name }}</td><td>{{ $order->salesperson?->name ?: '—' }}</td><td class="text-right">{{ number_format($order->total_amount,2) }}</td><td class="p-3 text-right"><a class="btn-select-order rounded-lg bg-purple-600 px-3 py-2 text-xs font-bold text-white hover:bg-purple-700 transition" href="{{ route('invoices.create',['order'=>$order->id]) }}" data-order-id="{{ $order->id }}" data-order-number="{{ $order->order_number }}">Select</a></td></tr>@empty<tr><td colspan="6" class="p-10 text-center text-slate-500">No open orders available.</td></tr>@endforelse</tbody></table></div>
</dialog>

<dialog id="order-conflict-dialog" class="invoice-flow-dialog order-conflict-dialog">
    <div class="order-conflict-card">
        <div class="order-conflict-icon-wrap">
            <span class="order-conflict-icon-exclamation">!</span>
        </div>
        <h2 class="order-conflict-title">Invoice will be cleared</h2>
        <p class="order-conflict-text">
            Order <strong id="conflict-order-number"></strong> has a different customer and sales person from this invoice, so it cannot be added to the existing items.
        </p>
        <p class="order-conflict-subtext">
            Clear the invoice and load this order instead?
        </p>
        <div class="order-conflict-actions">
            <button type="button" class="btn-conflict-clear-add" id="btn-conflict-clear-add">Clear and add</button>
            <button type="button" class="btn-conflict-cancel" id="btn-conflict-cancel">Cancel</button>
        </div>
    </div>
</dialog>

<dialog id="duplicate-item-dialog" class="invoice-flow-dialog duplicate-item-dialog">
    <div class="duplicate-item-card">
        <div class="duplicate-item-icon-wrap">
            <span class="duplicate-item-icon-question">?</span>
        </div>
        <h2 class="duplicate-item-title">Already on this invoice</h2>
        <div class="duplicate-item-subtitle" id="dup-item-subtitle"></div>
        <p class="duplicate-item-text">
            The same product and lot can have only one paid line and one free issue line.<br>
            Where should qty <span id="dup-item-qty">1</span> go?
        </p>
        <div class="duplicate-item-actions">
            <button type="button" class="btn-dup-paid" id="btn-dup-paid">Add to paid line</button>
            <button type="button" class="btn-dup-free" id="btn-dup-free">Add as Free Issue</button>
            <button type="button" class="btn-dup-cancel" id="btn-dup-cancel">Cancel</button>
        </div>
    </div>
</dialog>
@endif

@if($invoice->exists && $locked && $invoice->balance_due > 0)<dialog id="invoice-payment-dialog" class="invoice-payment-dialog"><form method="POST" action="{{ route('invoices.payments.store',$invoice) }}">@csrf<header><div><span><i class="bi bi-credit-card-2-front"></i></span><div><small>Register Payment</small><h2>{{ $invoice->invoice_number }}</h2></div></div><button type="button" data-close-payment><i class="bi bi-x-lg"></i></button></header><div class="payment-balance"><span>Amount due</span><strong>{{ number_format($invoice->balance_due,2) }} Rs</strong></div><div class="payment-form-grid"><label>Payment Journal<select name="payment_account_id" required><option value="">Select account</option>@foreach($paymentAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}{{ $account->account_number ? ' · '.$account->account_number : '' }}</option>@endforeach</select></label><label>Payment Date<input type="date" name="payment_date" value="{{ now()->format('Y-m-d') }}" required></label><label>Amount<input type="number" name="amount" min="0.01" max="{{ $invoice->balance_due }}" step="0.01" value="{{ $invoice->balance_due }}" required></label><label>Method<select name="method"><option>Cash</option><option>Bank Transfer</option><option>Card</option><option>Cheque</option><option>Mobile Wallet</option></select></label><label class="wide">Reference<input name="reference" placeholder="Transaction or receipt reference"></label><label class="wide">Memo<textarea name="note" rows="2" placeholder="Optional payment note"></textarea></label></div><footer><button type="button" data-close-payment>Cancel</button><button class="odoo-confirm">Create Payment</button></footer></form></dialog>@endif

@if($invoice->exists && $locked)
<dialog id="invoice-send-dialog" class="invoice-flow-dialog"><form method="POST" action="{{ route('invoices.send',$invoice) }}">@csrf
    <header><h2>Print &amp; Send</h2><button type="button" data-close-dialog aria-label="Close"><i class="bi bi-x-lg"></i></button></header>
    <div class="invoice-flow-content"><div class="invoice-delivery-options"><label><input type="checkbox" name="download" value="1"> Download</label><label><input type="checkbox" name="by_email" value="1" @checked((bool)$invoice->customer?->email)> by Email</label><label><input type="checkbox" name="by_post" value="1"> by Post</label></div>
        <label>To<input type="email" name="to" value="{{ $invoice->customer?->email }}" placeholder="customer@example.com"></label>
        <label>Subject<input name="subject" value="Invoice {{ $invoice->invoice_number }} from CodezaERP"></label>
        <label class="send-message-field"><span>Message</span><textarea name="message" rows="5">Dear {{ $invoice->customer?->name }},

Please find invoice {{ $invoice->invoice_number }} for {{ number_format((float)$invoice->total_amount,2) }} Rs attached.

Thank you.</textarea></label>
        <div class="send-meta-row"><div><div class="invoice-attachment"><i class="bi bi-file-earmark-pdf"></i><span>{{ str_replace('/', '_', $invoice->invoice_number) }}.pdf</span><i class="bi bi-check-lg attachment-ready" aria-label="Attachment ready"></i></div><button type="button" class="attachments-button"><i class="bi bi-paperclip"></i> Attachments</button></div><span class="send-template"><b>Email template</b> Invoice: Sending</span></div>
    </div><footer><button class="odoo-confirm" type="submit">Send</button><button type="button" data-close-dialog>Cancel</button></footer>
</form></dialog>
@endif
@if($invoice->exists)<dialog id="invoice-activity-dialog" class="invoice-flow-dialog"><form method="POST" action="{{ route('invoices.activities.store',$invoice) }}">@csrf<input type="hidden" name="type" value="note"><header><div><small>Communication history</small><h2 id="invoice-activity-title">Log note</h2></div><button type="button" data-close-dialog aria-label="Close"><i class="bi bi-x-lg"></i></button></header><div class="invoice-flow-content"><label>Details<textarea name="message" rows="5" maxlength="2000" required placeholder="Write a note about this invoice"></textarea></label></div><footer><button type="button" data-close-dialog>Cancel</button><button class="odoo-confirm" type="submit">Save</button></footer></form></dialog>@endif

@if($invoice->exists && $locked && (float)$invoice->credited_amount < (float)$invoice->total_amount)<dialog id="invoice-credit-note-dialog" class="invoice-flow-dialog"><form method="POST" action="{{ route('invoices.credit-notes.store',$invoice) }}">@csrf<header><div><small>Customer invoice</small><h2>Create Credit Note</h2></div><button type="button" data-close-dialog aria-label="Close"><i class="bi bi-x-lg"></i></button></header><div class="invoice-flow-content"><p>Issue a credit against {{ $invoice->invoice_number }}. Any amount beyond the outstanding balance becomes customer advance credit.</p><label>Credit amount<input type="number" name="amount" min="0.01" max="{{ round((float)$invoice->total_amount-(float)$invoice->credited_amount,2) }}" step="0.01" value="{{ round((float)$invoice->total_amount-(float)$invoice->credited_amount,2) }}" required></label><label>Reason<textarea name="reason" rows="3" maxlength="1000" required placeholder="Why is this credit being issued?"></textarea></label></div><footer><button type="button" data-close-dialog>Cancel</button><button type="submit" class="odoo-confirm">Create Credit Note</button></footer></form></dialog>@endif
<dialog id="payment-term-dialog" class="payment-term-dialog">
    <form method="dialog" class="payment-term-dialog-card">
        <header><div class="payment-term-heading"><span class="payment-term-icon"><i class="bi bi-calendar2-check"></i></span><div><span>Payment schedule</span><h2 id="payment-term-title">15 Days</h2><p>Your invoice collection timeline</p></div></div><button value="cancel" aria-label="Close"><i class="bi bi-x-lg"></i></button></header>
        <div class="payment-term-summary"><div><span>Invoice date</span><b id="payment-example-date"></b></div><i class="bi bi-arrow-right"></i><div><span>Payment term</span><b id="payment-term-summary-name"></b></div><i class="bi bi-arrow-right"></i><div class="is-due"><span>Final due date</span><b id="payment-final-date"></b></div></div>
        <div class="payment-term-detail-grid">
            <section><h3><i class="bi bi-list-check"></i> Due rules</h3><div class="payment-term-table-head"><span>Amount</span><span>Condition</span></div><div id="payment-term-rules"></div></section>
            <section><h3><i class="bi bi-signpost-split"></i> Installment preview</h3><div id="payment-term-preview" class="payment-term-preview"></div><small>Preview calculated using an example invoice total of 1,000.00 Rs.</small></section>
        </div>
        <footer><span><i class="bi bi-info-circle"></i> Due date updates automatically when the invoice date changes.</span><button value="cancel" class="odoo-confirm">Done</button></footer>
    </form>
</dialog>

<div id="catalog-workspace" class="odoo-catalog hidden">
    <div class="catalog-head">
        <div class="catalog-head-left">
            <button type="button" id="back-to-invoice" class="catalog-back-button">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Invoice</span>
            </button>
            <div class="catalog-head-divider"></div>
            <div class="catalog-title-block">
                <h2 class="catalog-main-title">Browse Products</h2>
                <span class="catalog-invoice-chip">
                    <i class="bi bi-receipt"></i>
                    {{ $invoice->invoice_number ?: 'Draft Invoice' }}
                </span>
                <button type="button" class="hidden"><i class="bi bi-table"></i> Spreadsheet</button>
            </div>
        </div>
        <div class="catalog-search-box">
            <i class="bi bi-search"></i>
            <input id="catalog-search" type="text" placeholder="Search products by name or code..." autocomplete="off">
        </div>
    </div>
    <div class="catalog-body"><aside class="catalog-summary"><h3>All products</h3><button type="button" class="active"><span>No Section</span><b id="catalog-section-total">0.00 Rs</b></button><div id="catalog-selected" class="catalog-selected"><p>No products selected</p></div><div class="catalog-running"><span>Total</span><b id="catalog-total">0.00 Rs</b></div><div class="catalog-filter-group"><h3>Categories</h3><button type="button" class="catalog-filter active" data-catalog-category="">All categories</button>@foreach($products->pluck('selectedCategory')->filter()->unique('id') as $category)<button type="button" class="catalog-filter" data-catalog-category="{{ $category->id }}">{{ $category->name }}</button>@endforeach</div></aside><div id="catalog-products" class="catalog-products">
        @forelse($products as $product)<article data-search="{{ strtolower($product->name.' '.$product->code) }}" data-category="{{ $product->selected_category_id }}" data-root-category="{{ $product->category_id }}"><div class="catalog-product-image">@if($product->image_path)<img src="{{ route('products.catalog.attachment',[$product,'image']) }}" alt="{{ $product->name }}" loading="lazy"><i class="bi bi-box-seam hidden" aria-hidden="true"></i>@else<i class="bi bi-box-seam" aria-hidden="true"></i>@endif</div><h4 title="{{ $product->name }}">{{ $product->name }}</h4><small title="{{ $product->code }}">{{ $product->code }}</small><strong>{{ number_format((float)$product->selling_price,2) }} Rs</strong><div><button type="button" class="catalog-minus" data-product="{{ $product->id }}" aria-label="Remove one {{ $product->name }}">−</button><output data-count="{{ $product->id }}">0</output><button type="button" class="catalog-plus" data-product="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->selling_price }}" data-tax="{{ $product->tax_rate_id }}" aria-label="Add one {{ $product->name }}">+</button></div></article>
        @empty<div class="catalog-empty"><i class="bi bi-file-earmark-text"></i><h2>No products could be found.</h2><a href="{{ route('products.catalog.create') }}">Create a product</a><p>You must define a product for everything you sell or purchase, whether it's a storable product, a consumable or a service.</p></div>@endforelse
        @if($products->isNotEmpty())<div id="catalog-filter-empty" class="catalog-filter-empty hidden"><i class="bi bi-search"></i><b>No matching products</b><span>Try another search or category.</span></div>@endif
    </div></div>
</div>

<template id="invoice-line-template"><div class="odoo-line" data-kind="product"><span class="line-grip" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span><span class="reference-line-type">Stock</span><span class="reference-line-code">—</span><input type="hidden" data-name="line_type" value="product"><input type="hidden" data-name="service_id" class="line-service-id" value=""><div class="line-details-cell"><select data-name="product_id" class="line-product" aria-label="Product"><option value="">Select...</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->selling_price }}" data-tax="{{ $product->tax_rate_id }}" data-code="{{ $product->code }}">{{ $product->id }} | {{ $product->name }} | {{ $product->code }}</option>@endforeach</select><div class="service-autocomplete-box hidden"><div class="service-placeholder-btn" role="button" tabindex="0" title="Click to select service"><span class="service-placeholder-text">Select Service...</span><i class="bi bi-chevron-down service-placeholder-arrow"></i></div><div class="service-input-wrap hidden"><i class="bi bi-gear-wide-connected service-icon"></i><input type="text" class="service-search-input" placeholder="Type to search service..." autocomplete="off"><button type="button" class="service-dropdown-arrow" tabindex="-1" title="Show services"><i class="bi bi-chevron-down"></i></button></div><div class="service-dropdown-list hidden"></div></div><div class="line-lot-picker-wrap hidden"><select class="line-lot-picker" aria-label="Select Lot"><option value="">Select Lot</option></select></div><div class="line-details-confirmed hidden"><span class="line-details-title"></span><button type="button" class="btn-item-info" title="Item info"><i class="bi bi-info-circle-fill"></i></button></div></div><select data-name="product_lot_id" class="line-lot" aria-label="Available lot" style="display: none;"><option value="">No available lots</option></select><input data-name="unit_price" aria-label="Sales Price" class="line-price" type="number" min="0" step="0.01" value="0.00"><input data-name="discount_percent" aria-label="Discount (%)" class="line-discount" type="number" min="0" max="100" step="any" value="0"><span class="line-discount-amount">0.00 Rs</span><span class="reference-line-available">0</span><input data-name="quantity" aria-label="Quantity" class="line-qty" type="number" min="0.0001" step="0.0001" value="1.00"><select data-name="tax_rate_id" aria-label="Tax" class="line-tax" style="display: none;"><option value="" data-rate="0">0%</option>@foreach($taxes as $tax)<option value="{{ $tax->id }}" data-rate="{{ $tax->amount }}">{{ rtrim(rtrim(number_format((float)$tax->amount, 3, '.', ''), '0'), '.') }}%</option>@endforeach</select><b class="line-amount">0.00 Rs</b><div class="line-actions-wrap"><button type="button" class="line-serial-btn" title="Select Serial Numbers"><i class="bi bi-record-circle"></i></button><button type="button" class="line-remove" aria-label="Remove line"><i class="bi bi-trash"></i></button></div><input type="hidden" data-name="description" class="line-description"><input type="hidden" data-name="selected_serials" class="line-selected-serials" value=""></div></template>

<dialog id="invoice-error-dialog" class="invoice-flow-dialog reference-custom-dialog">
    <div class="reference-dialog-card reference-error-card">
        <div class="reference-dialog-icon error">
            <i class="bi bi-x-lg"></i>
        </div>
        <h3 class="reference-dialog-title" id="reference-error-title">Error</h3>
        <p class="reference-dialog-msg" id="reference-error-message">Please select a customer.</p>
        <div class="reference-dialog-actions">
            <button type="button" class="reference-btn-ok" id="btn-close-error-dialog">OK</button>
        </div>
    </div>
</dialog>

<dialog id="invoice-confirm-dialog" class="invoice-flow-dialog reference-custom-dialog">
    <div class="reference-dialog-card reference-confirm-card">
        <p class="reference-dialog-msg" id="reference-confirm-message">Are you sure you want to delete this record?</p>
        <div class="reference-dialog-actions">
            <button type="button" class="reference-btn-yes" id="btn-confirm-yes">Yes</button>
            <button type="button" class="reference-btn-no" id="btn-confirm-no">No</button>
        </div>
    </div>
</dialog>

<dialog id="invoice-ledger-dialog" class="invoice-flow-dialog reference-ledger-dialog">
    <div class="reference-ledger-modal-card">
        <div class="reference-ledger-head">
            <div class="reference-ledger-title">
                <i class="bi bi-file-earmark-text"></i>
                <span>Ledger Preview</span>
            </div>
            <button type="button" class="reference-ledger-close" id="btn-close-ledger-top" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="reference-ledger-cards">
            <div class="reference-ledger-card">
                <div class="ref-card-icon debit">
                    <i class="bi bi-arrow-up"></i>
                </div>
                <span class="ref-card-label">Total Debit</span>
                <strong class="ref-card-value" id="ref-ledger-total-debit">0.00</strong>
            </div>
            <div class="reference-ledger-card">
                <div class="ref-card-icon credit">
                    <i class="bi bi-arrow-down"></i>
                </div>
                <span class="ref-card-label">Total Credit</span>
                <strong class="ref-card-value" id="ref-ledger-total-credit">0.00</strong>
            </div>
            <div class="reference-ledger-card">
                <div class="ref-card-icon balance">
                    <i class="bi bi-calculator"></i>
                </div>
                <span class="ref-card-label">Balance</span>
                <strong class="ref-card-value" id="ref-ledger-balance">0.00</strong>
            </div>
        </div>

        <div class="reference-ledger-table-wrap">
            <table class="reference-ledger-table">
                <thead>
                    <tr>
                        <th style="width: 14%;">Date</th>
                        <th style="width: 25%;">Account</th>
                        <th style="width: 11%; text-align: center;">Acc Code</th>
                        <th style="width: 32%;">Description</th>
                        <th style="width: 9%; text-align: right;">Debit</th>
                        <th style="width: 9%; text-align: right;">Credit</th>
                    </tr>
                </thead>
                <tbody id="reference-ledger-rows">
                </tbody>
                <tfoot>
                    <tr class="reference-ledger-total-row">
                        <td colspan="4" style="text-align: right;"><strong>Total:</strong></td>
                        <td style="text-align: right;"><strong id="ref-ledger-foot-debit">0.00</strong></td>
                        <td style="text-align: right;"><strong id="ref-ledger-foot-credit">0.00</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="reference-ledger-balanced-banner">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <h4>Ledger Balanced!</h4>
                <p>All entries are balanced and ready to post.</p>
            </div>
        </div>

        <div class="reference-ledger-footer">
            <button type="button" class="btn-ref-ledger-close" id="btn-close-ledger-bottom">
                <i class="bi bi-x-circle"></i>
                <span>Close</span>
            </button>
        </div>
    </div>
</dialog>

<dialog id="invoice-types-dialog" class="invoice-flow-dialog invoice-types-modal">
    <div class="invoice-types-card">
        <div class="invoice-types-head">
            <h3 class="invoice-types-title">Invoice Types</h3>
            <button type="button" id="btn-close-inv-types-x" class="invoice-types-close" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="invoice-types-body">
            <div class="invoice-types-row">
                <label for="modal-inv-type-select">Type</label>
                <div class="invoice-types-select-wrap">
                    <select id="modal-inv-type-select" class="invoice-types-select">
                        <option value="SALE">SALE</option>
                        <option value="Test Type">Test Type</option>
                        <option value="Test Type 2">Test Type 2</option>
                        <option value="A1">A1</option>
                        <option value="GS60">GS60</option>
                        <option value="Test001">Test001</option>
                        <option value="Test002">Test002</option>
                        <option value="A2">A2</option>
                        <option value="TEST YR">TEST YR</option>
                        <option value="Tax invoice">Tax invoice</option>
                        <option value="Sales" selected>Sales</option>
                        <option value="inv-type01">inv-type01</option>
                        <option value="Non-Tax invoice">Non-Tax invoice</option>
                        <option value="HP">HP</option>
                        <option value="Credit">Credit</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Repair">Repair</option>
                        <option value="Modification">Modification</option>
                        <option value="QuickService">QuickService</option>
                        <option value="Test automation">Test automation</option>
                        <option value="sample invoice">sample invoice</option>
                    </select>
                </div>
            </div>

            <div class="invoice-types-row">
                <label for="modal-revenue-ledger-input">Revenue Ledger</label>
                <input type="text" id="modal-revenue-ledger-input" class="invoice-types-input" readonly value="Sales">
            </div>
        </div>

        <div class="invoice-types-footer">
            <button type="button" id="btn-set-inv-type" class="btn-inv-type-set">Set</button>
            <button type="button" id="btn-close-inv-type" class="btn-inv-type-close">Close</button>
        </div>
    </div>
</dialog>

<!-- Select Serial Numbers Modal (Photo 4) -->
<dialog id="select-serials-dialog" class="invoice-flow-dialog select-serials-modal">
    <div class="select-serials-card">
        <div class="select-serials-header">
            <h3 class="select-serials-title">Select Serial Numbers</h3>
            <button type="button" id="btn-close-serials-dialog" class="select-serials-close" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="select-serials-body">
            <!-- Product Info Light Blue Banner (Photo 4) -->
            <div class="serials-product-banner">
                <div class="serials-banner-left">
                    <span class="serials-banner-lbl">Product:</span>
                    <strong id="serials-product-title">—</strong>
                </div>
                <div class="serials-banner-mid">
                    <span class="serials-banner-lbl">Required Qty:</span>
                    <strong id="serials-required-qty">1</strong>
                </div>
                <div class="serials-banner-right">
                    <span>Selected:</span>
                    <span class="serials-selected-badge" id="serials-selected-count">0</span>
                    <span id="serials-selected-total-label">/ 1</span>
                </div>
            </div>

            <!-- Search Bar & Buttons (Photo 4) -->
            <div class="serials-search-bar">
                <input type="text" id="serials-search-input" placeholder="Search serials..." autocomplete="off">
                <button type="button" id="btn-serials-find" class="btn-serials-action">Find</button>
                <button type="button" id="btn-serials-browse" class="btn-serials-action">Browse</button>
            </div>

            <!-- Serials Table (Photo 4) -->
            <div class="serials-table-container">
                <table class="serials-table">
                    <thead>
                        <tr>
                            <th style="width: 5%; text-align: center;">
                                <input type="checkbox" id="serials-check-all" title="Select all required">
                            </th>
                            <th style="width: 35%;">Serial Number</th>
                            <th style="width: 30%;">Description</th>
                            <th style="width: 15%; text-align: right;">Sales Price</th>
                            <th style="width: 15%; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="serials-table-body">
                        <!-- Populated dynamically via JS -->
                    </tbody>
                </table>
                <div id="serials-empty-message" class="serials-empty-state hidden">
                    <i class="bi bi-info-circle"></i>
                    <span>No serial numbers available for this product. You can type or upload serials.</span>
                </div>
            </div>
        </div>

        <!-- Footer with Upload on Left and Action buttons on Right (Photo 4) -->
        <div class="select-serials-footer">
            <div class="serials-footer-left">
                <div class="serials-file-wrap">
                    <input type="file" id="serials-excel-file" accept=".xlsx,.xls,.csv" class="serials-file-input">
                    <label for="serials-excel-file" class="btn-serials-choose" id="serials-choose-label">Choose File</label>
                    <span class="serials-file-chosen" id="serials-file-chosen-text">No file chosen</span>
                </div>
                <button type="button" id="btn-serials-upload" class="btn-serials-upload">Upload Excel</button>
            </div>
            <div class="serials-footer-right">
                <button type="button" id="btn-serials-cancel" class="btn-serials-cancel">Cancel</button>
                <button type="button" id="btn-serials-confirm" class="btn-serials-confirm">
                    <i class="bi bi-check-circle"></i> Confirm Selection
                </button>
            </div>
        </div>
    </div>
</dialog>

<!-- Product Item Info Card (Photo 4) -->
<div id="product-info-backdrop" class="product-info-backdrop"></div>
<div id="product-info-popup" class="product-info-popup" role="dialog" aria-modal="true" aria-labelledby="info-product-name">
    <div class="product-info-head">
        <div class="product-info-head-left">
            <i class="bi bi-info-circle product-info-head-icon"></i>
            <div class="product-info-head-text">
                <h3 id="info-product-name">Product Name</h3>
                <div class="sku-subtitle" id="info-product-code-sku">Code - Barcode</div>
            </div>
        </div>
        <button type="button" class="product-info-close-btn" id="btn-close-item-info" aria-label="Close">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="product-info-body">
        <div class="info-section-title">ITEM DETAILS</div>
        <table class="info-kv-table">
            <tbody>
                <tr><td class="kv-label">Description</td><td class="kv-val" id="info-desc">—</td></tr>
                <tr><td class="kv-label">Barcode</td><td class="kv-val" id="info-barcode">—</td></tr>
                <tr><td class="kv-label">Unit</td><td class="kv-val" id="info-unit">—</td></tr>
                <tr><td class="kv-label">Critical level</td><td class="kv-val" id="info-critical">1</td></tr>
            </tbody>
        </table>

        <div class="info-section-title">TYPE</div>
        <div>
            <span id="info-type-badge" class="info-pill-badge">Stock Item</span>
        </div>

        <div class="info-section-title">PROPERTIES</div>
        <div>
            <span id="info-prop-badge" class="info-pill-badge">Standard</span>
        </div>

        <div class="info-section-title">LEDGER ACCOUNTS</div>
        <div class="info-ledger-list">
            <div class="info-ledger-item">
                <div class="info-ledger-item-left"><span class="info-ledger-dot orange"></span> Stock</div>
                <div class="info-ledger-val" id="info-ledger-stock">Mashi Distributors Advance Creditor Ledger</div>
            </div>
            <div class="info-ledger-item">
                <div class="info-ledger-item-left"><span class="info-ledger-dot orange"></span> Cost of Sales</div>
                <div class="info-ledger-val" id="info-ledger-cogs">VAT Payable</div>
            </div>
            <div class="info-ledger-item">
                <div class="info-ledger-item-left"><span class="info-ledger-dot green"></span> Revenue</div>
                <div class="info-ledger-val" id="info-ledger-revenue">Sales</div>
            </div>
        </div>

        <div class="info-section-title">AVAILABLE LOTS</div>
        <table class="info-lots-table">
            <thead>
                <tr>
                    <th>Lot</th>
                    <th>Batch</th>
                    <th>Cost</th>
                    <th>Sale price</th>
                    <th>Min price</th>
                    <th>La.Price</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody id="info-lots-tbody">
                <!-- Populated dynamically via JS -->
            </tbody>
        </table>
    </div>
</div>

<template id="text-line-template"><div class="odoo-text-line" data-kind="note"><span class="reference-line-type">Text</span><span class="reference-line-code">—</span><input type="hidden" data-name="line_type" value="note"><input type="text" data-name="description" placeholder="Notes or instructions line..." class="line-description text-line-desc" autocomplete="off" required><button type="button" class="line-remove" aria-label="Remove line"><i class="bi bi-trash"></i></button></div></template>

<dialog id="quick-create-customer-dialog" class="quick-customer-modal">
    <div class="quick-customer-modal-card">
        <div class="quick-customer-modal-header">
            <div class="quick-customer-modal-title">
                <i class="bi bi-person-plus-fill"></i>
                <h3>Create New Customer</h3>
            </div>
            <button type="button" id="btn-close-quick-cust" class="quick-customer-modal-close" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form id="quick-create-customer-form">
            @csrf
            <input type="hidden" name="type" value="customer">
            <input type="hidden" name="entity_type" value="individual">
            <input type="hidden" name="status" value="active">

            <div id="quick-customer-feedback" class="quick-customer-feedback hidden"></div>

            <div class="quick-customer-modal-body">
                <div class="quick-cust-field">
                    <label>Customer Name <span class="req">*</span></label>
                    <input type="text" name="name" id="qc-name" required placeholder="e.g. Kasun Perera or ABC Hardware">
                </div>
                <div class="quick-cust-row">
                    <div class="quick-cust-field">
                        <label>Mobile Number <span class="req">*</span></label>
                        <input type="tel" name="mobile" id="qc-mobile" required placeholder="e.g. 0771234567">
                    </div>
                    <div class="quick-cust-field">
                        <label>Tax / VAT Number</label>
                        <input type="text" name="tax_number" id="qc-tax" placeholder="e.g. 102938475-7000">
                        <small class="qc-hint">If filled, marked as VAT Customer</small>
                    </div>
                </div>
                <div class="quick-cust-row">
                    <div class="quick-cust-field">
                        <label>Business Name (Optional)</label>
                        <input type="text" name="business_name" id="qc-business" placeholder="Business or shop name">
                    </div>
                    <div class="quick-cust-field">
                        <label>Email Address (Optional)</label>
                        <input type="email" name="email" id="qc-email" placeholder="customer@example.com">
                    </div>
                </div>
                <div class="quick-cust-field">
                    <label>Address / Location (Optional)</label>
                    <input type="text" name="address_line_1" id="qc-address" placeholder="e.g. No. 12, Main Street, Colombo">
                </div>
            </div>

            <div class="quick-customer-modal-footer">
                <button type="button" id="btn-cancel-quick-cust" class="btn-qc-cancel">Cancel</button>
                <button type="submit" id="btn-submit-quick-cust" class="btn-qc-save">
                    <i class="bi bi-check-lg"></i>
                    <span>Save & Select Customer</span>
                </button>
            </div>
        </form>
    </div>
</dialog>

@endsection

@push('scripts')
<style>
.odoo-invoice-shell{margin:-1rem;background:#1f2025;color:#e6e6e8;min-height:calc(100vh - 5rem);font-family:Arial,sans-serif;font-size:14px}.odoo-invoice-shell button,.odoo-invoice-shell input,.odoo-invoice-shell select,.odoo-invoice-shell textarea{font:inherit}.odoo-commandbar{height:108px;padding:18px 16px;display:flex;align-items:flex-start;position:relative}.odoo-titleblock{display:flex;gap:9px;align-items:center}.odoo-titleblock>div{display:flex;flex-direction:column;line-height:20px}.odoo-titleblock a{color:#00c7c7}.odoo-titleblock strong{font-weight:500}.odoo-new{border:1px solid #a84d92!important;border-radius:6px;color:#eee!important;padding:7px 12px}.odoo-kebab{font-size:22px;margin:0 11px}.odoo-titleblock i{margin:0 7px;font-size:17px}.odoo-journal{position:absolute;left:47%;top:17px;background:#292b32;border:1px solid #3c3f49;border-radius:7px;color:#eee;padding:7px 12px;font-weight:600}.odoo-actionbar{height:42px;padding:0 16px;display:flex;align-items:start;position:relative}.odoo-confirm{background:#9d4289;color:white;border:0;border-radius:5px;padding:8px 13px;font-weight:700;margin-right:6px}.odoo-cancel{background:#292b32;border:1px solid #41444e;color:#fff;border-radius:5px;padding:7px 12px;font-weight:700}.odoo-status{position:absolute;right:29px;top:0;background:#2b2d34;border:1px solid #3d414b;border-radius:6px;overflow:hidden}.odoo-status span{display:inline-block;padding:7px 13px;color:#9499a5}.odoo-status .active{background:#41545c;color:#fff;font-weight:700;clip-path:polygon(0 0,90% 0,100% 50%,90% 100%,0 100%)}.odoo-sheet{margin:0 16px;background:#292b32;border:1px solid #363943;min-height:620px}.odoo-document-type{display:block;margin:27px 24px 8px;color:#fff}.odoo-number{display:block;width:73%;margin:7px 24px 16px;padding:0 0 3px;background:transparent;border:0;border-bottom:1px solid #00b9b9;color:#aeb0b6;font-size:32px;outline:0}.odoo-header-grid{display:grid;grid-template-columns:1fr 1fr;column-gap:70px;padding:0 24px 55px}.odoo-field{display:flex;align-items:center;min-height:37px;gap:14px}.odoo-field label{font-weight:700;color:#fff;min-width:64px}.odoo-field input,.odoo-field select,.odoo-field textarea{background:transparent;border:0;border-bottom:1px solid #3d4048;color:#ddd;min-width:0;flex:1;padding:6px 2px;outline:none}.odoo-field select option,.odoo-line select option{background:#292b32;color:#eee}.odoo-field input:focus,.odoo-field select:focus{border-color:#00b9b9}.odoo-arrow{font-size:23px}.odoo-or{margin-left:auto}.odoo-tabs{border-bottom:1px solid #3b3e46;padding-left:25px;display:flex;height:39px;align-items:flex-end;position:relative}.odoo-tabs>button{height:38px;padding:0 16px;background:#363840;color:#eee;border:0}.odoo-tabs>button.active{background:#292b32;color:#d55fb8;border-top:2px solid #d55fb8}.tax-switch{position:absolute;right:24px;bottom:10px;border:1px solid #444751;border-radius:4px;overflow:hidden}.tax-switch button{background:#25272d;color:#ddd;border:0;padding:6px 9px}.tax-switch button.active{border:1px solid #00c7c7;color:white}.odoo-line-head{display:grid;grid-template-columns:minmax(260px,4fr) 1fr 1fr 1.2fr 1.2fr 28px;align-items:center;gap:8px;padding:0 24px 0 54px;border-bottom:1px solid #3a3d45;min-height:39px}.odoo-line{display:grid;grid-template-columns:22px minmax(230px,4fr) 1fr 1fr 1.2fr 1.2fr 28px;align-items:center;gap:8px;padding:0 24px;border-bottom:1px solid #3a3d45;min-height:39px}.odoo-line-head span:nth-child(5){text-align:right}.odoo-line input,.odoo-line select{width:100%;color:#eee;background:transparent;border:0;border-bottom:1px solid transparent;padding:7px;outline:0}.odoo-line input:focus,.odoo-line select:focus{border-color:#00b9b9}.line-grip{color:#9aa0aa;cursor:grab}.line-amount{text-align:right;font-weight:400}.line-remove{color:#ddd;background:none;border:0;font-size:15px}.odoo-add-row{height:41px;padding:0 53px;display:flex;align-items:center;gap:20px;border-bottom:1px solid #3a3d45}.odoo-add-row:focus-within{background:#29454c}.odoo-add-row button{border:0;background:none;color:#00c7c7}.odoo-spacer{height:112px;border-bottom:1px solid #3a3d45}.odoo-text-line{display:grid;grid-template-columns:22px 1fr 28px;gap:8px;padding:7px 24px;border-bottom:1px solid #4b4e57;background:#464850}.odoo-text-line[data-kind="note"]{background:#34363d}.odoo-text-line[data-kind="note"] input{font-style:italic;font-weight:400}.odoo-text-line input{color:#1e293b;font-weight:500;outline:0}.odoo-bottom{min-height:145px;padding:15px 28px;display:flex;justify-content:space-between}.odoo-bottom textarea{background:transparent;border:0;color:#ddd;resize:none;width:50%;padding:8px}.odoo-totals{width:270px}.odoo-totals>div{display:flex;justify-content:space-between;padding:5px 0}.odoo-totals .grand{font-size:17px;border-top:1px solid #555}.odoo-totals .amount-due{margin-top:9px;color:#aeb2bd;font-size:16px}.odoo-totals .amount-due b{color:white;font-size:18px}.odoo-totals sup{color:#54bad0}.odoo-other-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px 65px;padding:28px}.odoo-other-grid .wide{grid-column:1/-1}.odoo-flash,.odoo-errors{margin:0 16px 10px;padding:10px 14px;border-radius:4px}.odoo-flash{background:#245b48}.odoo-errors{background:#6c3038}.odoo-errors ul{list-style:disc;margin-left:20px}.odoo-chatter{height:49px;display:flex;align-items:center;gap:6px;padding:7px 16px}.odoo-chatter button,.catalog-head>button{background:#292b32;border:1px solid #41444e;border-radius:5px;color:white;padding:7px 11px;font-weight:700}.odoo-chatter button:first-child,.catalog-head>button{background:#9d4289;border-color:#9d4289}.odoo-chatter span{flex:1}.odoo-chatter i{margin:0 5px;color:#8cc9da}.odoo-chatter b{color:#12c6c6}.odoo-catalog{position:fixed;inset:0;z-index:100;background:#1f2025;color:#fff;padding:55px 16px 16px}.catalog-head{height:95px;display:flex;align-items:flex-start;gap:10px}.catalog-head>div{display:flex;flex-direction:column}.catalog-head em{color:#00c7c7;font-style:normal}.catalog-head label{position:absolute;left:34%;top:55px;width:32%;border:1px solid #00a7a7;border-radius:20px;padding:9px 15px}.catalog-head label input{background:transparent;border:0;outline:0;color:#eee;width:90%;margin-left:8px}.catalog-body{display:grid;grid-template-columns:268px 1fr;gap:28px}.catalog-body aside{padding-top:15px}.catalog-body aside h3{font-weight:700;margin-bottom:18px}.catalog-body aside button{display:block;width:100%;padding:8px;text-align:left;background:transparent;color:#ddd;border:0;border-radius:5px}.catalog-body aside button.active{background:#45474f}.catalog-running{display:flex;justify-content:space-between;border-top:1px solid #444;margin-top:20px;padding-top:26px}.catalog-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px;align-content:start}.catalog-products article{background:#2a2c32;border:1px solid #3f424b;border-radius:7px;padding:12px}.catalog-product-image{height:70px;display:grid;place-items:center;color:#777;font-size:38px}.catalog-products h4{font-weight:700}.catalog-products small{display:block;color:#999}.catalog-products strong{display:block;margin:10px 0}.catalog-products article>div:last-child{display:flex;align-items:center;justify-content:flex-end;gap:10px}.catalog-products article button{height:27px;width:27px;border-radius:4px;border:1px solid #555;background:#373941;color:#fff}.catalog-products .catalog-plus{background:#9d4289}.catalog-empty{grid-column:1/-1;text-align:center;padding:90px 10px}.catalog-empty i{font-size:80px;color:#a888a2}.catalog-empty h2{font-size:22px;font-weight:700;margin:10px}.catalog-empty a{display:inline-block;background:#9d4289;color:white;padding:9px 14px;border-radius:5px;font-weight:700}.catalog-empty p{margin:18px auto;max-width:650px;font-size:16px}.hidden{display:none!important}.odoo-invoice-shell fieldset:disabled{opacity:.78}.odoo-invoice-shell fieldset:disabled button{display:none}@media(max-width:800px){.odoo-header-grid{grid-template-columns:1fr;padding-bottom:30px}.odoo-line-head{grid-template-columns:minmax(210px,3fr) 80px 100px 110px 110px 28px;width:850px}.odoo-line{grid-template-columns:22px minmax(180px,3fr) 80px 100px 110px 110px 28px;width:850px}.odoo-tab-panel{overflow-x:auto}.odoo-commandbar{height:90px}.odoo-journal{display:none}.odoo-bottom{width:850px}.odoo-number{width:90%}.catalog-body{grid-template-columns:1fr}.catalog-body aside{display:none}.catalog-head label{left:auto;right:15px;width:45%}}
</style>
<style>
/* Codeza ERP invoice skin: Odoo workflow, native Codeza visual architecture. */
.odoo-invoice-shell{margin:0;background:transparent;color:#334155;min-height:0;font-family:inherit;font-size:14px}.odoo-commandbar{height:auto;min-height:74px;padding:16px 20px;background:#fff;border:1px solid #ede9fe;border-radius:16px 16px 0 0;align-items:center}.odoo-titleblock{gap:12px}.odoo-titleblock>div{line-height:19px}.odoo-titleblock a{color:#7c3aed;font-size:12px;font-weight:700}.odoo-titleblock strong{color:#0f172a;font-weight:800}.odoo-new{background:#7c3aed!important;border:0!important;border-radius:10px!important;color:#fff!important;padding:9px 14px;box-shadow:0 4px 12px rgb(124 58 237 / .2)}.odoo-kebab{color:#94a3b8}.odoo-titleblock i{color:#64748b}.odoo-journal{position:static;margin-left:auto;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;color:#475569;padding:9px 13px}.odoo-actionbar{height:auto;min-height:58px;padding:10px 20px;background:#fff;border-inline:1px solid #ede9fe;border-top:1px solid #f1f5f9;align-items:center}.odoo-confirm{background:#7c3aed;border-radius:10px;padding:9px 15px;box-shadow:0 4px 12px rgb(124 58 237 / .2)}.odoo-cancel{background:#fff;border:1px solid #cbd5e1;color:#475569;border-radius:10px;padding:8px 13px}.odoo-status{right:20px;top:12px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px}.odoo-status span{padding:7px 14px;color:#64748b}.odoo-status .active{background:#ede9fe;color:#6d28d9;border-radius:999px;clip-path:none}.odoo-sheet{margin:0;background:#fff;border:1px solid #ede9fe;border-radius:0 0 16px 16px;min-height:560px;box-shadow:0 10px 30px rgb(15 23 42 / .06)}.odoo-document-type{margin:26px 26px 8px;color:#7c3aed;font-size:12px;text-transform:uppercase;letter-spacing:.08em}.odoo-number{width:calc(100% - 52px);margin:4px 26px 22px;border:0;color:#0f172a;font-size:28px;font-weight:800}.odoo-header-grid{column-gap:64px;padding:0 26px 38px}.odoo-field{gap:12px}.odoo-field label{color:#334155;min-width:90px;font-size:12px}.odoo-field input,.odoo-field select,.odoo-field textarea{background:#fff;border:1px solid #e2e8f0;border-radius:9px;color:#334155;padding:9px 11px}.odoo-field select option,.odoo-line select option{background:#fff;color:#334155}.odoo-field input:focus,.odoo-field select:focus,.odoo-field textarea:focus{border-color:#8b5cf6;box-shadow:0 0 0 3px rgb(139 92 246 / .12)}.odoo-arrow{color:#7c3aed}.odoo-or{color:#94a3b8}.odoo-tabs{height:48px;padding-left:25px;background:#f8fafc;border-block:1px solid #e2e8f0;align-items:flex-end}.odoo-tabs>button{height:47px;padding:0 18px;background:transparent;color:#64748b;font-weight:700}.odoo-tabs>button.active{background:#fff;color:#7c3aed;border-top:0;border-bottom:2px solid #7c3aed}.tax-switch{right:24px;bottom:8px;border:1px solid #e2e8f0;border-radius:9px}.tax-switch button{background:#fff;color:#64748b;padding:7px 10px}.tax-switch button.active{background:#7c3aed;border:0;color:#fff}.odoo-line-head{min-height:43px;background:#fff;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.odoo-line{min-height:48px;border-bottom:1px solid #f1f5f9}.odoo-line:hover{background:#faf5ff}.odoo-line input,.odoo-line select{color:#334155;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:7px}.odoo-line input:focus,.odoo-line select:focus{border-color:#8b5cf6}.line-grip{color:#94a3b8}.line-remove{color:#e11d48}.odoo-add-row{height:48px;border-bottom:1px solid #e2e8f0;background:#fafafa}.odoo-add-row:focus-within{background:#faf5ff}.odoo-add-row button{color:#7c3aed;font-weight:700}.odoo-spacer{height:74px;border-bottom:1px solid #f1f5f9}.odoo-text-line{background:#f8fafc;border-bottom:1px solid #e2e8f0}.odoo-text-line[data-kind="note"]{background:#fff}.odoo-text-line input{color:#334155}.odoo-bottom{min-height:155px;background:#fff}.odoo-bottom textarea{color:#475569;border:1px solid transparent;border-radius:10px}.odoo-bottom textarea:focus{border-color:#e2e8f0;outline:none}.odoo-totals{color:#475569}.odoo-totals .grand{border-color:#e2e8f0;color:#0f172a}.odoo-totals .amount-due{color:#64748b}.odoo-totals .amount-due b{color:#0f172a}.odoo-flash,.odoo-errors{margin:0 0 12px;border-radius:12px}.odoo-flash{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857}.odoo-errors{background:#fff1f2;border:1px solid #fecdd3;color:#be123c}.odoo-chatter{height:auto;min-height:58px;margin-top:16px;padding:10px 14px;background:#fff;border:1px solid #ede9fe;border-radius:14px;box-shadow:0 4px 16px rgb(15 23 42 / .04)}.odoo-chatter button,.catalog-head>button{background:#fff;border:1px solid #cbd5e1;border-radius:9px;color:#475569}.odoo-chatter button:first-child,.catalog-head>button{background:#7c3aed;border-color:#7c3aed;color:#fff}.odoo-chatter i{color:#7c3aed}.odoo-chatter b{color:#7c3aed}.odoo-catalog{position:fixed;left:16rem;top:56px;right:0;bottom:0;z-index:35;background:#f8fafc;color:#334155;padding:24px;overflow:auto}.catalog-head{height:92px;align-items:flex-start}.catalog-head>div b{color:#0f172a}.catalog-head em{color:#7c3aed}.catalog-head label{top:24px;border:1px solid #cbd5e1;background:#fff;border-radius:12px}.catalog-head label input{color:#334155}.catalog-body{gap:24px}.catalog-body aside{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px;height:max-content}.catalog-body aside h3{color:#0f172a}.catalog-body aside button{color:#64748b}.catalog-body aside button.active{background:#f3e8ff;color:#7c3aed;font-weight:700}.catalog-running{border-color:#e2e8f0}.catalog-products article{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;box-shadow:0 2px 8px rgb(15 23 42 / .04)}.catalog-products article:hover{border-color:#c4b5fd;box-shadow:0 8px 18px rgb(124 58 237 / .1)}.catalog-products h4{color:#0f172a}.catalog-products article button{border-color:#cbd5e1;background:#fff;color:#475569}.catalog-products .catalog-plus{background:#7c3aed;border-color:#7c3aed;color:#fff}.catalog-empty i{color:#c4b5fd}.catalog-empty h2{color:#0f172a}.catalog-empty a{background:#7c3aed}.odoo-invoice-shell fieldset:disabled{opacity:.68}@media(max-width:1023px){.odoo-catalog{left:0}}@media(max-width:800px){.odoo-commandbar{min-height:64px}.odoo-header-grid{column-gap:0}.odoo-status{position:static;margin-left:auto}.odoo-actionbar{gap:6px}.catalog-head label{left:auto;right:24px}}
</style>
<style>
/* The application sidebar owns its desktop visibility. Keep invoice-local
   utility rules from overriding Tailwind's responsive `lg:flex` state. */
@media (min-width:1024px){#main-sidebar.hidden{display:flex!important}}
/* Match the reference form treatment: editable values sit on a single
   baseline instead of inside boxed controls. Codeza colors remain intact. */
.odoo-field input,.odoo-field select,.odoo-field textarea{
    background:transparent!important;
    border:0!important;
    border-bottom:1px solid #cbd5e1!important;
    border-radius:0!important;
    box-shadow:none!important;
    padding:8px 2px!important;
}
.odoo-field input:focus,.odoo-field select:focus,.odoo-field textarea:focus{
    border-bottom-color:#7c3aed!important;
    box-shadow:none!important;
    outline:none!important;
}
/* Invoice lines deliberately look like the reference grid, not a row of
   standalone form boxes. The whole row is the editing surface. */
.odoo-line{background:#fff!important}
.odoo-line:hover,.odoo-line:focus-within{background:#faf5ff!important}
.odoo-line input:focus,.odoo-line select:focus{outline:none}
.odoo-line .line-qty,.odoo-line .line-price{text-align:right;font-variant-numeric:tabular-nums}
.odoo-line .line-tax{
    justify-self:start;
    width:auto!important;
    max-width:78px;
    padding:4px 22px 4px 10px!important;
    border:1px solid #cbd5e1!important;
    border-radius:999px!important;
    background-color:#f8fafc!important;
    color:#475569!important;
    font-size:12px;
    font-weight:700;
}
.odoo-line .line-amount{padding-right:3px;color:#64748b;font-weight:500;font-variant-numeric:tabular-nums}
.odoo-line .line-remove{opacity:.72}.odoo-line .line-remove:hover{opacity:1;color:#be123c}
.catalog-title-row{display:flex;align-items:center;gap:8px}.catalog-actions{position:relative}.catalog-actions summary{display:grid;width:28px;height:28px;place-items:center;border:1px solid #c4b5fd;border-radius:8px;color:#7c3aed;cursor:pointer;list-style:none}.catalog-actions summary::-webkit-details-marker{display:none}.catalog-actions>div{position:absolute;z-index:5;top:34px;left:0;width:170px;padding:6px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 14px 30px rgb(15 23 42 / .16)}.catalog-actions button{display:flex;width:100%;align-items:center;gap:9px;padding:8px 9px;border:0;border-radius:7px;background:#fff;color:#475569;text-align:left}.catalog-actions button:hover{background:#f3e8ff;color:#6d28d9}.catalog-actions button .bi-chevron-right{margin-left:auto}.catalog-actions hr{margin:4px 0;border:0;border-top:1px solid #e2e8f0}.catalog-summary>button.active{display:flex;justify-content:space-between;align-items:center}.catalog-summary>button.active b{font-weight:600;color:#64748b}.catalog-selected{margin-top:10px}.catalog-selected p{padding:8px;color:#94a3b8;font-size:12px}.catalog-selected-row{display:grid;grid-template-columns:1fr auto;gap:3px 10px;padding:8px;border-radius:8px}.catalog-selected-row:hover{background:#faf5ff}.catalog-selected-row strong{overflow:hidden;color:#334155;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.catalog-selected-row span{color:#64748b;font-size:11px}.catalog-selected-row b{grid-column:2;grid-row:1/3;align-self:center;color:#334155;font-size:12px}
</style>
<style>
/* Compact operational density keeps the invoice visible on laptop screens. */
.odoo-invoice-shell{font-size:12.5px}.odoo-commandbar{min-height:58px;padding:10px 14px}.odoo-new{padding:7px 11px;font-size:12px}.odoo-titleblock{gap:9px}.odoo-titleblock>div{line-height:16px}.odoo-titleblock i{margin:0 4px;font-size:14px}.odoo-kebab{margin:0 6px;font-size:18px}.odoo-journal{padding:7px 10px;font-size:12px}.odoo-actionbar{min-height:47px;padding:7px 14px}.odoo-confirm{padding:7px 12px;font-size:12px}.odoo-cancel{padding:6px 11px;font-size:12px}.odoo-status{right:14px;top:7px}.odoo-status span{padding:6px 12px;font-size:12px}.odoo-sheet{min-height:0}.odoo-document-type{margin:17px 20px 5px;font-size:10px}.odoo-number{width:calc(100% - 40px);margin:2px 20px 12px;font-size:22px}.odoo-header-grid{column-gap:42px;padding:0 20px 20px}.odoo-field{min-height:31px;gap:8px}.odoo-field label{min-width:78px;font-size:10.5px}.odoo-field input,.odoo-field select,.odoo-field textarea{font-size:12px!important;padding:5px 2px!important}.odoo-arrow{font-size:17px}.odoo-tabs{height:41px;padding-left:18px}.odoo-tabs>button{height:40px;padding:0 14px;font-size:12px}.tax-switch{right:17px;bottom:5px}.tax-switch button{padding:6px 8px;font-size:11px}.odoo-line-head{min-height:34px;padding-left:45px;font-size:9.5px}.odoo-line{min-height:38px;padding-inline:18px}.odoo-line input,.odoo-line select{font-size:11.5px}.odoo-add-row{height:40px;padding:0 45px;gap:16px}.odoo-add-row button{display:inline-flex;align-items:center;gap:5px;font-size:11.5px}.odoo-add-row button i{font-size:12px}.odoo-spacer{height:34px}.odoo-bottom{min-height:112px;padding:11px 22px}.odoo-bottom textarea{padding:6px;font-size:11.5px}.odoo-totals{width:245px}.odoo-totals>div{padding:3px 0}.odoo-totals .grand{font-size:14px}.odoo-totals .amount-due{margin-top:5px;font-size:13px}.odoo-totals .amount-due b{font-size:15px}.odoo-other-grid{gap:12px 38px;padding:18px 22px}.odoo-chatter{min-height:48px;margin-top:10px;padding:7px 10px}.odoo-chatter button{padding:6px 9px;font-size:11.5px}.catalog-head>button{padding:7px 10px;font-size:12px}#catalog-invoice-breadcrumb{padding:0;border:0;background:none;color:#7c3aed;cursor:pointer;font:inherit;text-decoration:none}#catalog-invoice-breadcrumb:hover,#catalog-invoice-breadcrumb:focus-visible{text-decoration:underline}@media(max-width:800px){.odoo-header-grid{padding:0 15px 16px}.odoo-number{margin-inline:15px;width:calc(100% - 30px)}.odoo-add-row{padding-inline:20px;gap:11px}.odoo-add-row button{font-size:11px}}
.other-info-section h3{margin-bottom:9px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;color:#475569;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.other-info-section .odoo-field label{min-width:125px;color:#334155}.other-info-section .odoo-field{min-height:36px}.field-question{color:#7c3aed;font-size:10px}.other-info-operations{grid-column:1/-1;margin-top:8px;padding-top:4px}.other-info-operations>div{display:grid;grid-template-columns:140px minmax(0,1fr)}.other-info-operations .odoo-field label{min-width:125px}@media(max-width:800px){.other-info-section .odoo-field label{min-width:105px}.other-info-operations{grid-column:auto}.other-info-operations>div{display:flex}}
.payment-term-control{display:flex;min-width:0;flex:1;align-items:center;border-bottom:1px solid #cbd5e1}.payment-term-control input{border-bottom:0!important}.payment-term-open{display:none;flex:0 0 28px;width:28px;height:28px;place-items:center;border:0;background:transparent;color:#7c3aed;border-radius:7px}.payment-term-open:hover{background:#f3e8ff}.odoo-due-field.has-payment-term #invoice-due-date,.odoo-due-field.has-payment-term .odoo-or{display:none}.odoo-due-field.has-payment-term .payment-term-open{display:grid}.odoo-due-field.has-payment-term .payment-term-control{border-bottom-color:#8b5cf6}.payment-term-dialog{width:min(920px,calc(100vw - 32px));padding:0;border:0;border-radius:16px;background:transparent;color:#334155;box-shadow:0 24px 70px rgb(15 23 42 / .25)}.payment-term-dialog::backdrop{background:rgb(15 23 42 / .48);backdrop-filter:blur(2px)}.payment-term-dialog-card{background:#fff;border:1px solid #e9e5ff;border-radius:16px;overflow:hidden}.payment-term-dialog header{display:flex;align-items:flex-start;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #ede9fe}.payment-term-dialog header span{color:#7c3aed;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.payment-term-dialog h2{margin:4px 0 0;color:#0f172a;font-size:25px}.payment-term-dialog header button{display:grid;width:32px;height:32px;place-items:center;border:0;border-radius:8px;background:#f8fafc;color:#64748b}.payment-term-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:30px;padding:25px}.payment-term-detail-grid h3{margin:0 0 18px;padding-bottom:8px;border-bottom:1px solid #e2e8f0;color:#475569;font-size:11px;text-transform:uppercase;letter-spacing:.06em}.payment-term-table-head,.payment-term-rule{display:grid;grid-template-columns:130px 1fr;gap:12px;padding:9px 10px}.payment-term-table-head{color:#64748b;border-bottom:1px solid #e2e8f0;font-size:11px}.payment-term-rule{color:#334155;border-bottom:1px solid #f1f5f9}.payment-preview-check{display:flex;align-items:center;gap:8px;font-weight:700}.payment-preview-check input{accent-color:#7c3aed}.payment-term-detail-grid p{margin:16px 0;color:#64748b}.payment-term-preview{padding:14px;border-left:3px solid #7c3aed;border-radius:5px;background:#f8fafc}.payment-term-preview strong{display:block;margin-bottom:8px;color:#334155}.payment-term-preview div+div{margin-top:6px}.payment-term-preview b{color:#6d28d9}.payment-term-dialog footer{display:flex;justify-content:flex-end;padding:12px 24px;border-top:1px solid #ede9fe}.payment-term-dialog footer .odoo-confirm{margin:0}@media(max-width:700px){.payment-term-detail-grid{grid-template-columns:1fr}.payment-term-table-head,.payment-term-rule{grid-template-columns:105px 1fr}}
/* Compact payment schedule: centred, scannable and visually tied to the ERP. */
.payment-term-dialog{width:min(760px,calc(100vw - 32px));margin:auto!important;border-radius:20px;box-shadow:0 30px 90px rgb(30 27 75 / .28)}.payment-term-dialog-card{border-radius:20px}.payment-term-dialog header{padding:18px 20px;background:linear-gradient(135deg,#faf7ff 0%,#fff 70%)}.payment-term-heading{display:flex;align-items:center;gap:12px}.payment-term-heading .payment-term-icon{display:grid;width:42px;height:42px;place-items:center;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff!important;font-size:18px!important;box-shadow:0 8px 18px rgb(124 58 237 / .22)}.payment-term-heading h2{margin:1px 0 0;font-size:20px}.payment-term-heading p{margin:2px 0 0;color:#94a3b8;font-size:11px}.payment-term-dialog header>button:hover{background:#ede9fe;color:#6d28d9}.payment-term-summary{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:center;gap:10px;margin:18px 20px 0;padding:13px;border:1px solid #ede9fe;border-radius:14px;background:#fcfbff}.payment-term-summary>div{min-width:0;padding:2px 8px}.payment-term-summary span{display:block;margin-bottom:4px;color:#94a3b8;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.payment-term-summary b{display:block;overflow:hidden;color:#334155;font-size:12px;text-overflow:ellipsis;white-space:nowrap}.payment-term-summary>i{color:#c4b5fd}.payment-term-summary .is-due{padding:8px 10px;border-radius:10px;background:#f3e8ff}.payment-term-summary .is-due span{color:#8b5cf6}.payment-term-summary .is-due b{color:#6d28d9}.payment-term-detail-grid{gap:20px;padding:20px}.payment-term-detail-grid section{min-width:0;padding:15px;border:1px solid #eef2f7;border-radius:14px;background:#fff}.payment-term-detail-grid h3{display:flex;align-items:center;gap:7px;margin-bottom:8px;padding-bottom:10px;color:#334155}.payment-term-detail-grid h3 i{color:#7c3aed;font-size:14px}.payment-term-table-head,.payment-term-rule{grid-template-columns:90px 1fr;padding:9px 6px}.payment-term-rule span:first-child{color:#7c3aed;font-weight:800}.payment-term-preview{padding:4px 0 4px 18px;border:0;background:transparent;position:relative}.payment-term-preview:before{content:"";position:absolute;top:8px;bottom:8px;left:4px;width:2px;background:#ddd6fe}.payment-term-preview>div{position:relative;padding:10px 12px;border:1px solid #ede9fe;border-radius:10px;background:#faf7ff;color:#475569}.payment-term-preview>div:before{content:"";position:absolute;left:-19px;top:15px;width:9px;height:9px;border:2px solid #fff;border-radius:50%;background:#8b5cf6;box-shadow:0 0 0 2px #c4b5fd}.payment-term-preview strong{display:none}.payment-term-detail-grid section>small{display:block;margin:10px 2px 0;color:#94a3b8;font-size:9.5px;line-height:1.4}.payment-term-dialog footer{align-items:center;justify-content:space-between;padding:11px 20px;background:#fafafa}.payment-term-dialog footer>span{display:flex;align-items:center;gap:6px;color:#94a3b8;font-size:10px}.payment-term-dialog footer>span i{color:#8b5cf6}.payment-term-dialog footer .odoo-confirm{border-radius:9px;padding:7px 18px}@media(max-width:700px){.payment-term-summary{grid-template-columns:1fr}.payment-term-summary>i{display:none}.payment-term-detail-grid{grid-template-columns:1fr}.payment-term-dialog footer>span{display:none}.payment-term-dialog footer{justify-content:flex-end}}
.odoo-header-grid>.odoo-field:first-child{grid-column:1;grid-row:1}.invoice-customer-details{grid-column:1;grid-row:2/4;margin:2px 0 0 86px;padding:9px 0 2px;border-top:1px solid #f1f5f9}.invoice-date-field{grid-column:2;grid-row:1}.odoo-due-field{grid-column:2;grid-row:2}.odoo-header-grid>.odoo-field:last-child{grid-column:2;grid-row:3}.customer-detail-section{display:grid;grid-template-columns:28px 1fr;gap:8px;padding:6px 0}.customer-detail-icon{display:grid;width:25px;height:25px;place-items:center;border-radius:7px;background:#f3e8ff;color:#7c3aed;font-size:11px}.customer-detail-section small{display:block;margin-bottom:2px;color:#94a3b8;font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.customer-detail-section strong{display:block;color:#334155;font-size:11.5px}.customer-detail-section p{margin:2px 0;color:#64748b;font-size:10.5px;line-height:1.45;white-space:pre-line}.customer-detail-meta{display:flex;flex-wrap:wrap;gap:5px;margin-top:5px}.customer-detail-meta span{display:inline-flex;align-items:center;gap:4px;padding:3px 7px;border-radius:999px;background:#f8fafc;color:#64748b;font-size:9.5px}.customer-detail-meta i{color:#8b5cf6}.customer-delivery-section{margin-top:3px;padding-top:8px;border-top:1px dashed #e2e8f0}@media(max-width:800px){.odoo-header-grid>.odoo-field:first-child,.invoice-customer-details,.invoice-date-field,.odoo-due-field,.odoo-header-grid>.odoo-field:last-child{grid-column:1;grid-row:auto}.invoice-customer-details{margin-left:86px}}
.odoo-actionbar{gap:6px;padding-right:250px}.invoice-action{display:inline-flex;align-items:center;gap:5px;padding:6px 10px;border:1px solid #d8dee9;border-radius:8px;background:#fff;color:#475569;font-size:11px;font-weight:700}.invoice-action:hover{border-color:#c4b5fd;background:#faf5ff;color:#6d28d9}.invoice-action.primary{background:#7c3aed;border-color:#7c3aed;color:#fff}.odoo-status .warning{background:#fff7ed;color:#c2410c}.odoo-status .paid{background:#dcfce7;color:#15803d}.journal-table{min-height:250px;padding:12px 20px 35px}.journal-head,.journal-table>div{display:grid;grid-template-columns:1.4fr 1.5fr .7fr .7fr;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #eef2f7}.journal-head{background:#f8fafc;color:#64748b;font-size:10px;font-weight:800;text-transform:uppercase}.journal-table>div span:nth-last-child(-n+2),.journal-table>div strong:nth-last-child(-n+2){text-align:right}.journal-table>div span b{margin-right:6px;color:#7c3aed}.journal-total{background:#faf7ff}.invoice-payment-history{margin:0 22px 18px auto;width:min(480px,calc(100% - 44px));padding:12px 15px;border:1px solid #dcfce7;border-radius:12px;background:#f7fff9}.invoice-payment-history h3{margin-bottom:7px;color:#15803d;font-size:10px;text-transform:uppercase}.invoice-payment-history>div{display:grid;grid-template-columns:28px 1fr auto;gap:8px;align-items:center;padding:6px 0}.payment-ok{display:grid;width:23px;height:23px;place-items:center;border-radius:50%;background:#22c55e;color:#fff}.invoice-payment-history p b,.invoice-payment-history p small{display:block}.invoice-payment-history p small{color:#64748b;font-size:9.5px}.invoice-payment-dialog{width:min(610px,calc(100vw - 30px));margin:auto;padding:0;border:0;border-radius:18px;box-shadow:0 30px 90px rgb(30 27 75/.3)}.invoice-payment-dialog::backdrop{background:rgb(15 23 42/.5);backdrop-filter:blur(2px)}.invoice-payment-dialog form{background:#fff;color:#334155}.invoice-payment-dialog header{display:flex;justify-content:space-between;padding:18px 20px;background:#faf7ff;border-bottom:1px solid #ede9fe}.invoice-payment-dialog header>div{display:flex;gap:11px;align-items:center}.invoice-payment-dialog header>div>span{display:grid;width:40px;height:40px;place-items:center;border-radius:11px;background:#7c3aed;color:#fff;font-size:17px}.invoice-payment-dialog header small{color:#7c3aed;font-weight:800;text-transform:uppercase}.invoice-payment-dialog h2{margin:1px 0;font-size:17px}.invoice-payment-dialog header button{border:0;background:transparent;color:#64748b}.payment-balance{display:flex;justify-content:space-between;margin:18px 20px 0;padding:12px 15px;border-radius:11px;background:#f3e8ff;color:#6d28d9}.payment-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px;padding:18px 20px}.payment-form-grid label{color:#475569;font-size:10px;font-weight:800}.payment-form-grid input,.payment-form-grid select,.payment-form-grid textarea{display:block;width:100%;margin-top:5px;padding:8px 9px;border:1px solid #dbe2ea;border-radius:8px;color:#334155;font:inherit}.payment-form-grid .wide{grid-column:1/-1}.invoice-payment-dialog footer{display:flex;justify-content:flex-end;gap:8px;padding:12px 20px;border-top:1px solid #eef2f7}.invoice-payment-dialog footer button{padding:7px 13px;border:1px solid #dbe2ea;border-radius:8px;background:#fff}.invoice-payment-dialog footer .odoo-confirm{background:#7c3aed;color:#fff}.invoice-activity-feed{margin-top:-10px;padding:10px 15px 15px;background:#fff;border:1px solid #ede9fe;border-top:0;border-radius:0 0 14px 14px}.invoice-activity-feed article{display:grid;grid-template-columns:30px 1fr;gap:9px;padding:9px;border-bottom:1px solid #f1f5f9}.invoice-activity-feed article>span{display:grid;width:27px;height:27px;place-items:center;border-radius:50%;background:#f3e8ff;color:#7c3aed}.invoice-activity-feed small{margin-left:8px;color:#94a3b8;font-size:9px}.invoice-activity-feed p{margin-top:3px;color:#64748b;font-size:10.5px}@media(max-width:800px){.odoo-actionbar{padding-right:10px;flex-wrap:wrap}.odoo-status{position:static}.payment-form-grid{grid-template-columns:1fr}.payment-form-grid .wide{grid-column:1}}
.odoo-invoice-shell fieldset:disabled{opacity:1}.odoo-invoice-shell fieldset:disabled input,.odoo-invoice-shell fieldset:disabled select,.odoo-invoice-shell fieldset:disabled textarea{color:#475569;-webkit-text-fill-color:#475569}
.odoo-invoice-shell[data-invoice-locked] .odoo-add-row,.odoo-invoice-shell[data-invoice-locked] .line-remove{display:none}.odoo-invoice-shell[data-invoice-locked] input:disabled,.odoo-invoice-shell[data-invoice-locked] select:disabled,.odoo-invoice-shell[data-invoice-locked] textarea:disabled{color:#475569;-webkit-text-fill-color:#475569;opacity:1}.odoo-invoice-shell[data-invoice-locked] .tax-switch button:disabled{cursor:default}.journal-table{overflow-x:auto}.journal-head,.journal-table>.journal-item,.journal-table>.journal-total{min-width:780px;grid-template-columns:1.5fr 1.5fr 1fr .75fr .75fr .7fr}.journal-table>.journal-item:nth-child(odd){background:#fbfaff}.journal-table .journal-muted{color:#cbd5e1}.journal-table>.journal-empty{display:block;padding:35px 12px;text-align:center;color:#94a3b8}
.journal-table>div>:nth-child(4),.journal-table>div>:nth-child(5){text-align:right}
.journal-preview-note{display:block!important;min-width:780px;padding:10px 12px;border-bottom:1px solid #ede9fe;background:#faf7ff;color:#7c3aed;font-size:11px;font-weight:600}.journal-table #draft-journal-rows>.journal-item{display:grid;min-width:780px;grid-template-columns:1.5fr 1.5fr 1fr .75fr .75fr .7fr;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #eef2f7}.journal-table #draft-journal-rows>.journal-item:nth-child(odd){background:#fbfaff}.journal-table #draft-journal-rows>.journal-item>:nth-child(4),.journal-table #draft-journal-rows>.journal-item>:nth-child(5){text-align:right}.journal-table #draft-journal-rows>.journal-item span b{margin-right:6px;color:#7c3aed}.journal-table #draft-journal-rows>.journal-empty{min-width:780px}
/* Keep every journal header and row on the same six-column grid. */
.journal-table>.journal-head,.journal-table>.journal-item,.journal-table>.journal-total,.journal-table>#draft-journal-rows>.journal-item{display:grid;box-sizing:border-box;width:max(100%,780px);min-width:780px;grid-template-columns:1.5fr 1.5fr 1fr .75fr .75fr .7fr;grid-auto-flow:row;gap:12px;align-items:center}
.journal-table>.journal-head>*{min-width:0;white-space:nowrap}.journal-table>.journal-head>:nth-child(4),.journal-table>.journal-head>:nth-child(5),.journal-table>.journal-item>:nth-child(4),.journal-table>.journal-item>:nth-child(5),.journal-table>.journal-total>:nth-child(4),.journal-table>.journal-total>:nth-child(5){text-align:right}
/* Posted invoices are a read-only document, so do not render their values like editable fields. */
.odoo-invoice-shell[data-invoice-locked] .odoo-header-grid>.odoo-field{min-height:34px;gap:10px}
.odoo-invoice-shell[data-invoice-locked] .odoo-header-grid>.odoo-field input,.odoo-invoice-shell[data-invoice-locked] .odoo-header-grid>.odoo-field select{padding:4px 0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;color:#334155;font-weight:600;appearance:none}
.odoo-invoice-shell[data-invoice-locked] .odoo-header-grid>.odoo-field label{color:#475569}
.odoo-invoice-shell[data-invoice-locked] .odoo-header-grid>.odoo-field:first-child select{font-weight:700}
.odoo-invoice-shell[data-invoice-locked] .odoo-arrow,.odoo-invoice-shell[data-invoice-locked] .odoo-or,.odoo-invoice-shell[data-invoice-locked] .payment-term-open{display:none}
.odoo-invoice-shell[data-invoice-locked] .payment-term-control{display:flex;flex:1}
.odoo-invoice-shell[data-invoice-locked] .odoo-due-field:has(#invoice-due-date[value=""]):has(#invoice-payment-term[value=""]) #invoice-due-date,.odoo-invoice-shell[data-invoice-locked] .odoo-due-field:has(#invoice-due-date[value=""]):has(#invoice-payment-term[value=""]) .payment-term-control{display:none}
.odoo-invoice-shell[data-invoice-locked] .odoo-due-field:has(#invoice-due-date[value=""]):has(#invoice-payment-term[value=""])::after{content:"—";color:#94a3b8;font-weight:600}
.odoo-invoice-shell[data-invoice-locked] .invoice-customer-details{border-top:0;padding-top:2px}
.odoo-invoice-shell[data-invoice-locked] .customer-delivery-section{border-top:0;padding-top:6px}
.odoo-invoice-shell[data-invoice-locked] .locked-customer-name{display:block;min-width:0;flex:1;padding:4px 0;color:#334155;font-weight:700;line-height:1.4}
.invoice-installments{width:min(470px,calc(100% - 44px));margin:0 22px 18px auto;padding:12px 14px;border:1px solid #e2e8f0;border-radius:10px;background:#fafafa}.invoice-installments h3{margin-bottom:6px;color:#334155;font-size:10px;text-transform:uppercase}.invoice-installments>div{display:grid;grid-template-columns:1fr auto 62px;gap:12px;align-items:center;padding:6px 0;border-top:1px solid #eef2f7}.invoice-installments em{padding:3px 6px;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:9px;font-style:normal;text-align:center}.invoice-installments em.paid{background:#dcfce7;color:#15803d}.invoice-installments em.partial{background:#fef3c7;color:#92400e}
.invoice-toast{position:fixed;right:1.25rem;top:.75rem;z-index:90;display:flex;align-items:flex-start;gap:.75rem;width:min(300px,calc(100vw - 2rem));padding:1rem;border-radius:.375rem;color:#fff;font-size:.875rem;font-weight:600;box-shadow:0 12px 28px rgb(15 23 42 / .22);transition:opacity .25s ease,transform .25s ease}.invoice-toast.is-success{background:#059669}.invoice-toast.is-error{background:#c9534f}.invoice-toast>i{flex:0 0 auto;font-size:1.5rem;line-height:1.25}.invoice-toast p{min-width:0;flex:1;margin:0;line-height:1.4;overflow-wrap:anywhere}.invoice-toast small{display:block;margin-top:.25rem;color:rgb(255 255 255 / .82);font-size:.72rem}.invoice-toast button{flex:0 0 auto;margin:-.25rem -.25rem 0 0;padding:.25rem;border:0;background:transparent;color:#fff;opacity:.8;cursor:pointer}.invoice-toast button:hover{opacity:1}.invoice-toast.is-dismissing{opacity:0;transform:translateY(-8px)}
 </style>
<style>
.invoice-workspace{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:14px;align-items:start}.invoice-main{min-width:0}.invoice-side{min-width:0;position:sticky;top:24px;max-height:none;overflow:visible}.odoo-invoice-shell .odoo-sheet{position:relative;overflow:hidden}.invoice-paid-ribbon{position:absolute;right:-41px;top:23px;width:150px;padding:8px;background:#16a34a;color:#fff;text-align:center;font-weight:800;transform:rotate(45deg)}.invoice-payment-count{align-self:center;margin-left:auto;color:#6d28d9;font-weight:700}.invoice-credit-notice{display:flex;align-items:center;gap:8px;margin:8px 0;padding:9px 13px;border:1px solid #a5e4ef;border-radius:8px;background:#e9faff;color:#0e7490}.invoice-available-credit{width:min(380px,calc(100% - 44px));margin:0 22px 20px auto;padding:12px;border-top:1px solid #e2e8f0}.invoice-available-credit h3{font-size:12px;color:#334155;font-weight:800}.invoice-available-credit>div{display:grid;grid-template-columns:auto 1fr auto;gap:8px;align-items:center;margin:8px 0}.invoice-available-credit button{border:0;border-radius:5px;padding:4px 8px;background:#ede9fe;color:#6d28d9;font-weight:700}.invoice-available-credit small{color:#64748b}.invoice-communication,.invoice-preview-panel{background:#fff;border:1px solid #ede9fe;border-radius:12px;box-shadow:0 4px 16px rgb(15 23 42/.04)}.invoice-communication .odoo-chatter{display:flex;flex-wrap:wrap;gap:5px;margin:0;border:0;border-bottom:1px solid #ede9fe;border-radius:12px 12px 0 0;box-shadow:none}.invoice-communication .odoo-chatter button{padding:6px 8px;font-size:10px}.invoice-communication .odoo-chatter button:disabled{opacity:.5;cursor:not-allowed}.invoice-communication .invoice-activity-feed{margin:0;min-height:180px;border:0;border-radius:0 0 12px 12px}.invoice-activity-empty{padding:28px 8px;color:#94a3b8;text-align:center}.invoice-preview-panel{height:calc(100vh - 90px);overflow:hidden}.invoice-side-heading{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #ede9fe;color:#334155}.invoice-side-heading button{border:0;background:transparent;color:#64748b}.invoice-preview-panel iframe{width:100%;height:calc(100% - 39px);border:0;background:#fff}.invoice-workspace.preview-open{grid-template-columns:minmax(0,1fr) minmax(320px,42%)}.invoice-workspace.preview-open .invoice-communication{display:none}.invoice-flow-dialog{width:min(700px,calc(100vw - 30px));margin:auto;padding:0;border:0;border-radius:14px;box-shadow:0 30px 90px rgb(30 27 75/.3);color:#334155}.invoice-flow-dialog::backdrop{background:rgb(15 23 42/.52);backdrop-filter:blur(2px)}.invoice-flow-dialog header,.invoice-flow-dialog footer{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #e2e8f0}.invoice-flow-dialog header small{color:#7c3aed;font-size:10px;font-weight:800;text-transform:uppercase}.invoice-flow-dialog h2{font-size:19px;font-weight:700}.invoice-flow-dialog header button{border:0;background:transparent;color:#64748b}.invoice-flow-content{display:grid;gap:12px;padding:16px 18px}.invoice-flow-content>label{display:block;color:#475569;font-size:11px;font-weight:700}.invoice-flow-content>label input,.invoice-flow-content textarea{display:block;width:100%;margin-top:5px;padding:9px 10px;border:1px solid #dbe2ea;border-radius:8px;color:#334155;font:inherit;font-size:12px}.invoice-flow-content textarea{resize:vertical}.invoice-delivery-options{display:flex;gap:18px;padding:8px 10px;background:#faf7ff;border-radius:8px}.invoice-delivery-options label{display:flex;align-items:center;gap:5px;cursor:pointer}.invoice-attachment{display:flex;align-items:center;gap:8px;padding:9px;border:1px dashed #c4b5fd;border-radius:8px;color:#6d28d9}.invoice-attachment small{margin-left:auto;color:#64748b}.invoice-flow-dialog footer{justify-content:flex-end;gap:8px;border-top:1px solid #e2e8f0;border-bottom:0}.invoice-flow-dialog footer button{border-radius:7px;padding:7px 12px}.invoice-flow-dialog footer button:not(.odoo-confirm){border:1px solid #cbd5e1;background:#fff;color:#475569}@media(max-width:1250px){.invoice-workspace{grid-template-columns:1fr}.invoice-side{position:static;max-height:none}.invoice-workspace.preview-open{grid-template-columns:1fr}.invoice-preview-panel{height:70vh}}@media(max-width:800px){.odoo-actionbar{padding-right:10px}.invoice-payment-count{display:none}.invoice-delivery-options{flex-wrap:wrap}}
.catalog-filter-group{margin-top:22px;padding-top:14px;border-top:1px solid #e2e8f0}.catalog-filter-group h3{margin-bottom:8px}.catalog-summary .catalog-filter{padding:7px 9px;font-size:11px}.catalog-summary .catalog-filter.active{background:#f3e8ff;color:#6d28d9;font-weight:800}.catalog-product-image img{display:block;width:100%;height:70px;object-fit:contain}.invoice-main .odoo-actionbar{display:flex;align-items:center;flex-wrap:wrap;gap:5px;height:auto;min-height:48px;padding:7px 12px}.invoice-main .odoo-status{position:static;margin-left:auto}.invoice-main .odoo-tab-panel{overflow-x:auto}.invoice-main .odoo-sheet{margin:0}.invoice-main .odoo-header-grid{column-gap:20px}
.odoo-catalog{padding:14px 18px 20px}.catalog-head{display:grid;grid-template-columns:auto minmax(160px,1fr) minmax(190px,290px);gap:12px;height:62px;align-items:center}.catalog-head>button{white-space:nowrap;padding:7px 10px}.catalog-head>div{min-width:0;line-height:17px;font-size:11px}.catalog-head>div b{font-size:14px}.catalog-head label{position:static;width:100%;padding:6px 10px;border-radius:9px}.catalog-head label input{width:calc(100% - 25px);font-size:12px}.catalog-body{grid-template-columns:200px minmax(0,1fr);gap:12px}.catalog-body aside{position:sticky;top:0;padding:12px;border-radius:10px}.catalog-body aside h3{margin:0 0 8px;font-size:12px}.catalog-summary>button.active{min-height:30px;padding:5px 7px;font-size:11px}.catalog-selected{margin-top:5px}.catalog-selected p{padding:4px;font-size:10px}.catalog-running{margin-top:7px;padding-top:8px;font-size:11px}.catalog-filter-group{margin-top:12px;padding-top:10px}.catalog-summary .catalog-filter{padding:5px 7px;font-size:10.5px}.catalog-products{grid-template-columns:repeat(auto-fill,minmax(152px,1fr));gap:9px}.catalog-products article{display:flex;flex-direction:column;min-width:0;min-height:166px;padding:10px;border-radius:10px;box-shadow:0 1px 5px rgb(15 23 42 / .035)}.catalog-product-image{height:43px;margin-bottom:5px;font-size:23px;color:#94a3b8}.catalog-product-image img{height:43px;max-width:100%;object-fit:contain}.catalog-products h4{display:-webkit-box;min-height:30px;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;font-size:11.5px;line-height:15px}.catalog-products small{overflow:hidden;max-width:100%;color:#94a3b8;font-size:9.5px;text-overflow:ellipsis;white-space:nowrap}.catalog-products strong{margin:5px 0 8px;font-size:11.5px}.catalog-products article>div:last-child{gap:7px;margin-top:auto;font-size:11px}.catalog-products article button{width:24px;height:24px;border-radius:6px;font-size:13px}.catalog-filter-empty{grid-column:1/-1;display:grid;gap:7px;place-content:center;min-height:180px;color:#64748b;text-align:center}.catalog-filter-empty i{font-size:26px;color:#a78bfa}.catalog-filter-empty b{color:#334155}.catalog-filter-empty span{font-size:11px}@media(max-width:800px){.catalog-head{grid-template-columns:auto 1fr;height:auto;min-height:68px}.catalog-head label{grid-column:1/-1;position:static;width:100%}.catalog-body{grid-template-columns:1fr}.catalog-body aside{display:block}.catalog-filter-group{display:flex;flex-wrap:wrap;gap:5px}.catalog-filter-group h3{width:100%}.catalog-summary .catalog-filter{width:auto}.catalog-products{grid-template-columns:repeat(auto-fill,minmax(145px,1fr))}}
#invoice-send-dialog{width:min(900px,calc(100vw - 32px));min-height:510px;border-radius:3px;box-shadow:0 18px 55px rgb(15 23 42/.34)}#invoice-send-dialog::backdrop{background:rgb(15 23 42/.55);backdrop-filter:none}#invoice-send-dialog form{display:flex;min-height:510px;flex-direction:column}#invoice-send-dialog header{min-height:52px;padding:12px 14px;border-bottom:1px solid #dfe3e8}#invoice-send-dialog header h2{margin:0;font-size:16px;font-weight:700}#invoice-send-dialog header button{width:28px;height:28px;border-radius:0;background:transparent}#invoice-send-dialog .invoice-flow-content{display:block;flex:1;padding:16px 14px 24px}#invoice-send-dialog .invoice-delivery-options{gap:16px;padding:3px 0 8px;border-radius:0;background:transparent}#invoice-send-dialog .invoice-delivery-options label{font-size:12px}#invoice-send-dialog .invoice-flow-content>label{display:grid;grid-template-columns:58px minmax(0,1fr);align-items:center;min-height:32px;color:#475569;font-size:11px}#invoice-send-dialog .invoice-flow-content>label input,#invoice-send-dialog .invoice-flow-content>label textarea{margin:0;padding:6px 4px;border:0;border-radius:0;background:#fff;box-shadow:none;font-size:12px}#invoice-send-dialog .invoice-flow-content>label:first-of-type input{justify-self:start;width:auto;min-width:230px;padding:4px 10px;border-radius:14px;background:#eeeeef;color:#334155}#invoice-send-dialog .send-message-field{display:block!important;padding-top:8px}#invoice-send-dialog .send-message-field>span{display:none}#invoice-send-dialog .invoice-flow-content textarea{width:100%;min-height:185px;padding:6px 4px!important;line-height:1.75;resize:vertical}#invoice-send-dialog .send-meta-row{display:flex;align-items:flex-start;gap:30px;padding:2px 4px}#invoice-send-dialog .invoice-attachment{display:flex;width:250px;max-width:100%;margin:0;padding:7px 10px;border:0;border-radius:2px;background:#f4f4f5;color:#475569}#invoice-send-dialog .invoice-attachment .attachment-ready{margin-left:auto;color:#16a34a;font-weight:800}#invoice-send-dialog .attachments-button{margin-top:6px;padding:6px 9px;border:0;border-radius:2px;background:#ededf1;color:#475569;font-size:11px}#invoice-send-dialog .send-template{margin:7px 0 0 auto;color:#64748b;font-size:10px}#invoice-send-dialog .send-template b{margin-right:10px;color:#334155}#invoice-send-dialog footer{justify-content:flex-start;gap:6px;padding:12px 14px;border-top:1px solid #dfe3e8;background:#fafafa}#invoice-send-dialog footer button{padding:7px 13px;border-radius:2px}#invoice-send-dialog footer .odoo-confirm{margin:0;background:#71405f;box-shadow:none}@media(max-width:600px){#invoice-send-dialog{min-height:0}#invoice-send-dialog form{min-height:0}#invoice-send-dialog .invoice-flow-content>label{grid-template-columns:1fr}#invoice-send-dialog .send-meta-row{display:block}#invoice-send-dialog .send-template{display:block;margin:12px 0 0}}
</style>
<style>
.odoo-line-head{grid-template-columns:minmax(220px,3fr) minmax(170px,2fr) .8fr 1fr 1fr 1.1fr 28px}
.odoo-line{grid-template-columns:22px minmax(200px,3fr) minmax(170px,2fr) .8fr 1fr 1fr 1.1fr 28px}
.line-lot:disabled{color:#94a3b8!important;background:#f8fafc!important}
@media(max-width:800px){.odoo-line-head{grid-template-columns:minmax(210px,3fr) 180px 80px 100px 110px 110px 28px;width:1040px}.odoo-line{grid-template-columns:22px minmax(180px,3fr) 180px 80px 100px 110px 110px 28px;width:1040px}.odoo-bottom{width:1040px}}
</style>
<script>
(() => {
const initializeInvoiceForm = () => {
 const invoiceToast=document.getElementById('invoice-toast');
 if(invoiceToast){let toastTimer;const dismissToast=()=>{window.clearTimeout(toastTimer);invoiceToast.classList.add('is-dismissing');window.setTimeout(()=>invoiceToast.remove(),260)};invoiceToast.querySelector('button')?.addEventListener('click',dismissToast);toastTimer=window.setTimeout(dismissToast,7000)}
 
  const errorDialog = document.getElementById('invoice-error-dialog');
  const errorMsg = document.getElementById('reference-error-message');
  const btnCloseError = document.getElementById('btn-close-error-dialog');
  window.showInvoiceError = function(msg) {
    if (errorMsg) errorMsg.textContent = msg;
    if (errorDialog) {
      if (typeof errorDialog.showModal === 'function') errorDialog.showModal();
      else errorDialog.setAttribute('open', '');
    }
  };
  if (btnCloseError && errorDialog) {
    btnCloseError.addEventListener('click', () => errorDialog.close());
    errorDialog.addEventListener('click', (e) => { if (e.target === errorDialog) errorDialog.close(); });
  }

  const confirmDialog = document.getElementById('invoice-confirm-dialog');
  const confirmMsg = document.getElementById('reference-confirm-message');
  const btnConfirmYes = document.getElementById('btn-confirm-yes');
  const btnConfirmNo = document.getElementById('btn-confirm-no');
  let currentConfirmCallback = null;
  let currentCancelCallback = null;

  window.showInvoiceConfirm = function(msg, onYes, onNo) {
    if (confirmMsg) confirmMsg.textContent = msg;
    currentConfirmCallback = onYes;
    currentCancelCallback = onNo;
    if (confirmDialog) {
      if (typeof confirmDialog.showModal === 'function') confirmDialog.showModal();
      else confirmDialog.setAttribute('open', '');
    }
  };

  if (btnConfirmYes && confirmDialog) {
    btnConfirmYes.addEventListener('click', () => {
      confirmDialog.close();
      if (typeof currentConfirmCallback === 'function') {
        const cb = currentConfirmCallback;
        currentConfirmCallback = null;
        currentCancelCallback = null;
        cb();
      }
    });
  }
  if (btnConfirmNo && confirmDialog) {
    btnConfirmNo.addEventListener('click', () => {
      confirmDialog.close();
      if (typeof currentCancelCallback === 'function') {
        const cb = currentCancelCallback;
        currentConfirmCallback = null;
        currentCancelCallback = null;
        cb();
      }
    });
  }
  if (confirmDialog) {
    confirmDialog.addEventListener('click', (e) => {
      if (e.target === confirmDialog) {
        confirmDialog.close();
        if (typeof currentCancelCallback === 'function') {
          const cb = currentCancelCallback;
          currentConfirmCallback = null;
          currentCancelCallback = null;
          cb();
        }
      }
    });
  }

 
  // Invoice Types Modal Handling (Photo 2, 3, 4)
  const invTypeTrigger = document.getElementById('btn-trigger-inv-type');
  const invTypeDialog = document.getElementById('invoice-types-dialog');
  const invTypeSelect = document.getElementById('modal-inv-type-select');
  const revenueLedgerInput = document.getElementById('modal-revenue-ledger-input');
  const invTypeDisplay = document.getElementById('inv-type-display-value');
  const invoiceJournalInput = document.getElementById('invoice-journal-input');
  const btnSetInvType = document.getElementById('btn-set-inv-type');
  const btnCloseInvType = document.getElementById('btn-close-inv-type');
  const btnCloseInvTypeX = document.getElementById('btn-close-inv-types-x');

  const revenueLedgerMapping = {
    'SALE': 'Sales',
    'Sales': 'Sales',
    'Tax invoice': 'Sales',
    'Non-Tax invoice': 'Sales',
    'Credit': 'Sales',
    'Maintenance': 'Maintenance',
    'Repair': 'Repair',
    'Modification': 'Modification',
    'QuickService': 'QuickService',
  };

  if (invTypeTrigger && invTypeDialog) {
    invTypeTrigger.addEventListener('click', (e) => {
      e.preventDefault();
      const currentVal = invoiceJournalInput?.value || 'Sales';
      if (invTypeSelect) {
        invTypeSelect.value = currentVal;
        if (revenueLedgerInput) {
          revenueLedgerInput.value = revenueLedgerMapping[currentVal] || currentVal || 'Sales';
        }
      }
      if (typeof invTypeDialog.showModal === 'function') invTypeDialog.showModal();
      else invTypeDialog.setAttribute('open', '');
    });

    invTypeSelect?.addEventListener('change', () => {
      const selected = invTypeSelect.value;
      if (revenueLedgerInput) {
        revenueLedgerInput.value = revenueLedgerMapping[selected] || selected || 'Sales';
      }
    });

    const closeInvTypeModal = () => {
      if (typeof invTypeDialog.close === 'function') invTypeDialog.close();
      else invTypeDialog.removeAttribute('open');
    };

    btnSetInvType?.addEventListener('click', () => {
      const chosen = invTypeSelect ? invTypeSelect.value : 'Sales';
      if (invTypeDisplay) invTypeDisplay.textContent = chosen;
      if (invoiceJournalInput) {
        invoiceJournalInput.value = chosen;
        invoiceJournalInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      closeInvTypeModal();
    });

    btnCloseInvType?.addEventListener('click', closeInvTypeModal);
    btnCloseInvTypeX?.addEventListener('click', closeInvTypeModal);
    invTypeDialog.addEventListener('click', (e) => {
      if (e.target === invTypeDialog) closeInvTypeModal();
    });
  }

 const host=document.getElementById('invoice-lines'), productTpl=document.getElementById('invoice-line-template'), textTpl=document.getElementById('text-line-template');
 const invoiceLocked=@json($locked);
 const saved=@json($savedLines);
 const invoiceNumberPreviews=@json($invoiceNumberPreviews);
 if(!@json($invoice->exists)){
   document.querySelector('[name="location_id"]')?.addEventListener('change',event=>{
     const number=invoiceNumberPreviews[event.target.value||'default']||invoiceNumberPreviews.default;
     document.getElementById('invoice-number-display').textContent=number;
     document.querySelector('.odoo-number').value=number;
     calc();
   });
 }
 const availableLots=@json($availableLots);
 const customerProfiles=@json($customerProfiles);
 let index=0;
 const customerSelect=document.querySelector('[name="customer_id"]'),customerDetails=document.getElementById('invoice-customer-details');
 function syncCustomerDetails(){
   if(!customerDetails || !customerSelect) return;
   const customer=customerProfiles[customerSelect.value];
   customerDetails.classList.toggle('hidden',!customer);
   if(!customer)return;
   const dName = document.getElementById('customer-detail-name');
   if (dName) dName.textContent=customer.business&&customer.business!==customer.name?`${customer.business} · ${customer.name}`:customer.name;
   const dAddr = document.getElementById('customer-detail-address');
   if (dAddr) dAddr.textContent=customer.address||'No billing address added';
   const dDel = document.getElementById('customer-delivery-address');
   if (dDel) dDel.textContent=customer.delivery||'No delivery address added';
   const meta=document.getElementById('customer-detail-meta');
   if (meta) {
     meta.replaceChildren();
     [['bi-person-badge',customer.contact_id],['bi-telephone',customer.mobile],['bi-envelope',customer.email],['bi-receipt',customer.tax_number]].forEach(([icon,value])=>{if(!value)return;const item=document.createElement('span'),symbol=document.createElement('i');symbol.className=`bi ${icon}`;item.append(symbol,document.createTextNode(value));meta.appendChild(item)});
   }
 }
 function syncReferenceCustomer(){
    const c=customerProfiles[customerSelect.value]||{},
          host=document.getElementById('reference-customer-details'),
          badgeContainer=document.getElementById('customer-vat-badge-container');

    if(badgeContainer){
      badgeContainer.replaceChildren();
      if(customerSelect.value && c.name){
        const badge=document.createElement('div');
        if(c.is_vat){
          badge.className='vat-pill is-vat';
          badge.innerHTML='<i class="bi bi-check2"></i> VAT Customer';
        } else {
          badge.className='vat-pill is-non-vat';
          badge.innerHTML='<i class="bi bi-dash"></i> Non-VAT Customer';
        }
        badgeContainer.appendChild(badge);
      }
    }

    if(host){
      host.replaceChildren();
      if(!customerSelect.value || !c.name){
        const empty=document.createElement('div');
        empty.className='customer-empty-state';
        empty.innerHTML='<div class="customer-empty-icon"><i class="bi bi-person-lines-fill"></i></div><h4>No Customer Selected</h4><p>Select a customer to view address, credit limits & history</p>';
        host.appendChild(empty);
        return;
      }

      const rows = [
        ['Name', c.name, 'name'],
        ['Address', c.address, 'address'],
        ['Mobile', c.mobile, 'mobile'],
        ['Email', c.email, 'email'],
        ['Debit Limit', 'LKR ' + (c.credit_limit || '0.00'), 'limit'],
        ['Balance', 'LKR ' + (c.advance_balance || '0.00'), 'advance'],
        ['Total Due', 'LKR ' + (c.due_balance || '0.00'), 'due']
      ];

      rows.forEach(([label, value, type]) => {
        const row = document.createElement('div');
        row.className = 'customer-detail-row' + (type ? ' is-' + type : '');
        const lbl = document.createElement('span');
        lbl.className = 'customer-detail-label';
        lbl.textContent = label;
        const val = document.createElement('span');
        val.className = 'customer-detail-value' + (type ? ' ' + type : '');
        val.textContent = value;
        row.append(lbl, val);
        host.appendChild(row);
      });

      const lastInvoicesSection = document.createElement('div');
      lastInvoicesSection.className = 'customer-last-invoices';
      const heading = document.createElement('h3');
      heading.textContent = 'Last 5 Invoices';
      lastInvoicesSection.appendChild(heading);

      const invoices = c.last_invoices || [];
      if(invoices.length === 0){
        const noInv = document.createElement('p');
        noInv.className='customer-empty-state';
        noInv.textContent = 'No previous invoices';
        lastInvoicesSection.appendChild(noInv);
      } else {
        invoices.forEach(inv => {
          const item = document.createElement('div');
          item.className = 'customer-invoice-item';
          item.innerHTML = `<div><strong>${inv.number}</strong><small>${inv.date}</small></div><span class="inv-amount">LKR ${inv.amount}</span>`;
          lastInvoicesSection.appendChild(item);
        });
      }
      host.appendChild(lastInvoicesSection);
    }
  }
 customerSelect.addEventListener('change',()=>{syncCustomerDetails();syncReferenceCustomer()});

  // Searchable Customer Dropdown & Quick Create Customer Setup
  const customerSearchInput = document.getElementById('reference-customer-search');
  const customerDropdownMenu = document.getElementById('reference-customer-menu');
  const customerArrowBtn = document.getElementById('reference-customer-arrow-btn');
  const customerList = document.getElementById('reference-customer-list');
  const customerEmpty = document.getElementById('reference-customer-empty');
  const openQuickCustomerBtn = document.getElementById('btn-open-create-customer');
  const quickCustomerDialog = document.getElementById('quick-create-customer-dialog');
  const closeQuickCustomerBtn = document.getElementById('btn-close-quick-cust');
  const cancelQuickCustomerBtn = document.getElementById('btn-cancel-quick-cust');
  const quickCustomerForm = document.getElementById('quick-create-customer-form');
  const quickCustomerFeedback = document.getElementById('quick-customer-feedback');
  const submitQuickCustomerBtn = document.getElementById('btn-submit-quick-cust');

  if (customerSearchInput && customerDropdownMenu && customerSelect) {
    const showCustomerMenu = () => {
      customerDropdownMenu.classList.remove('hidden');
    };
    const hideCustomerMenu = () => {
      customerDropdownMenu.classList.add('hidden');
    };
    const toggleCustomerMenu = () => {
      customerDropdownMenu.classList.toggle('hidden');
    };

    // Pre-fill input value if customer is preselected
    if (customerSelect.value && customerProfiles[customerSelect.value]) {
      customerSearchInput.value = customerProfiles[customerSelect.value].name;
    }

    customerSearchInput.addEventListener('focus', () => {
      // Show all customer items when opening
      customerList.querySelectorAll('.reference-customer-item').forEach(item => item.style.display = 'grid');
      if (customerEmpty) customerEmpty.classList.add('hidden');
      showCustomerMenu();
    });
    customerSearchInput.addEventListener('click', (e) => { e.stopPropagation(); showCustomerMenu(); });
    if (customerArrowBtn) {
      customerArrowBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleCustomerMenu();
        if (!customerDropdownMenu.classList.contains('hidden')) {
          customerSearchInput.focus();
        }
      });
    }

    // Live search filtering
    customerSearchInput.addEventListener('input', () => {
      showCustomerMenu();
      const query = customerSearchInput.value.trim().toLowerCase();
      let matchCount = 0;
      customerList.querySelectorAll('.reference-customer-item').forEach(item => {
        const text = (item.dataset.search || '').toLowerCase();
        const matches = !query || text.includes(query);
        item.style.display = matches ? 'grid' : 'none';
        if (matches) matchCount++;
      });
      if (customerEmpty) customerEmpty.classList.toggle('hidden', matchCount > 0);
    });

    // Select customer from list
    customerList.addEventListener('mousedown', (e) => e.preventDefault());
    customerList.addEventListener('click', (e) => {
      const item = e.target.closest('.reference-customer-item');
      if (!item) return;
      const id = item.dataset.id;
      const name = item.dataset.name;

      const applyCustomer = () => {
        customerSelect.value = id;
        customerSearchInput.value = name;
        customerList.querySelectorAll('.reference-customer-item').forEach(el => el.classList.remove('is-selected'));
        item.classList.add('is-selected');
        hideCustomerMenu();
        customerSelect.dispatchEvent(new Event('change', { bubbles: true }));
      };

      if (customerSelect.value && String(customerSelect.value) !== String(id)) {
        hideCustomerMenu();
        if (window.showInvoiceConfirm) {
          window.showInvoiceConfirm('Are you sure you want to change this record?', () => {
            applyCustomer();
          }, () => {
            if (customerProfiles[customerSelect.value]) {
              customerSearchInput.value = customerProfiles[customerSelect.value].name;
            }
          });
        } else {
          applyCustomer();
        }
      } else {
        applyCustomer();
      }
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
      const wrap = document.getElementById('reference-customer-dropdown');
      if (wrap && !wrap.contains(e.target)) {
        hideCustomerMenu();
        if (customerSelect.value && customerProfiles[customerSelect.value]) {
          customerSearchInput.value = customerProfiles[customerSelect.value].name;
        } else if (!customerSelect.value) {
          customerSearchInput.value = '';
        }
      }
    });

    // Quick create customer modal
    if (openQuickCustomerBtn && quickCustomerDialog) {
      openQuickCustomerBtn.addEventListener('mousedown', (e) => e.preventDefault());
      openQuickCustomerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        hideCustomerMenu();
        if (quickCustomerFeedback) {
          quickCustomerFeedback.classList.add('hidden');
          quickCustomerFeedback.textContent = '';
        }
        if (quickCustomerForm) quickCustomerForm.reset();
        const currentQuery = customerSearchInput.value.trim();
        const nameInput = document.getElementById('qc-name');
        if (nameInput && currentQuery && !customerSelect.value) {
          nameInput.value = currentQuery;
        }
        quickCustomerDialog.showModal();
      });
    }

    if (closeQuickCustomerBtn && quickCustomerDialog) {
      closeQuickCustomerBtn.addEventListener('click', () => quickCustomerDialog.close());
    }
    if (cancelQuickCustomerBtn && quickCustomerDialog) {
      cancelQuickCustomerBtn.addEventListener('click', () => quickCustomerDialog.close());
    }

    // AJAX form submission for quick create
    if (quickCustomerForm) {
      quickCustomerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (submitQuickCustomerBtn) {
          submitQuickCustomerBtn.disabled = true;
          submitQuickCustomerBtn.innerHTML = '<span>Saving...</span>';
        }
        if (quickCustomerFeedback) {
          quickCustomerFeedback.classList.add('hidden');
          quickCustomerFeedback.textContent = '';
        }

        const formData = new FormData(quickCustomerForm);
        try {
          const res = await fetch("{{ route('contacts.store') }}", {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || formData.get('_token')
            },
            body: formData
          });

          const data = await res.json();
          if (!res.ok) {
            let errorText = data.message || 'Validation error occurred.';
            if (data.errors) {
              errorText = Object.values(data.errors).flat().join('<br>');
            }
            if (quickCustomerFeedback) {
              quickCustomerFeedback.innerHTML = errorText;
              quickCustomerFeedback.className = 'quick-customer-feedback is-error';
              quickCustomerFeedback.classList.remove('hidden');
            }
            return;
          }

          if (data.success && data.contact) {
            const c = data.contact;
            // Add to customerProfiles
            customerProfiles[c.id] = c;

            // Add option to customerSelect
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            customerSelect.appendChild(opt);
            customerSelect.value = c.id;

            // Prepend new row in customerList
            const row = document.createElement('div');
            row.className = 'reference-customer-item is-selected';
            row.dataset.id = c.id;
            row.dataset.name = c.name;
            row.dataset.mobile = c.mobile || '';
            row.dataset.address = c.address || '';
            row.dataset.search = (c.name + ' ' + (c.mobile||'') + ' ' + (c.address||'') + ' ' + (c.business||'')).toLowerCase();
            row.innerHTML = `<span class="ref-cust-name">${c.name}</span><span class="ref-cust-mobile">${c.mobile || '—'}</span><span class="ref-cust-address" title="${c.address}">${c.address || '—'}</span>`;

            customerList.querySelectorAll('.reference-customer-item').forEach(el => el.classList.remove('is-selected'));
            customerList.prepend(row);

            // Update search input text
            customerSearchInput.value = c.name;

            // Trigger change event to refresh VAT pill and right details card
            customerSelect.dispatchEvent(new Event('change', { bubbles: true }));

            // Close modal
            quickCustomerDialog.close();
          }
        } catch (err) {
          console.error(err);
          if (quickCustomerFeedback) {
            quickCustomerFeedback.textContent = 'Server error occurred while creating customer.';
            quickCustomerFeedback.className = 'quick-customer-feedback is-error';
            quickCustomerFeedback.classList.remove('hidden');
          }
        } finally {
          if (submitQuickCustomerBtn) {
            submitQuickCustomerBtn.disabled = false;
            submitQuickCustomerBtn.innerHTML = '<i class="bi bi-check-lg"></i> <span>Save & Select Customer</span>';
          }
        }
      });
    }
  }syncCustomerDetails();syncReferenceCustomer();
 function names(row){
   if(row.classList.contains('odoo-line')&&!row.querySelector('.line-discount')){
     const discount=document.createElement('input');
     discount.type='number';discount.min='0';discount.max='100';discount.step='0.0001';discount.value='0';
     discount.dataset.name='discount_percent';discount.className='line-discount';discount.setAttribute('aria-label','Discount (%)');
     const amount=document.createElement('span');amount.className='line-discount-amount';amount.textContent='0.00 Rs';
     row.append(discount,amount);
   }
   row.querySelectorAll('[data-name]').forEach(el=>el.name=`lines[${index}][${el.dataset.name}]`);index++;
 }
 function syncLots(row,selected=''){const productId=row.querySelector('.line-product').value,lotSelect=row.querySelector('.line-lot'),locationId=document.querySelector('[name="location_id"]')?.value||'';const lots=(availableLots[productId]||[]).filter(lot=>!locationId||String(lot.location_id)===String(locationId));lotSelect.replaceChildren();const placeholder=document.createElement('option');placeholder.value='';placeholder.textContent=lots.length?'Select lot':'No available lots';lotSelect.appendChild(placeholder);lots.forEach(lot=>{const option=document.createElement('option');option.value=lot.id;option.textContent=`${lot.number} · ${lot.available} available · ${lot.location}${lot.expires_at?' · Exp '+lot.expires_at:''}`;lotSelect.appendChild(option)});lotSelect.disabled=!lots.length;if(selected&&lots.some(lot=>String(lot.id)===String(selected)))lotSelect.value=selected}
 function add(kind='product',data={}){const tpl=(kind==='product'||kind==='service')?productTpl:textTpl,row=tpl.content.firstElementChild.cloneNode(true);row.dataset.kind=kind;row.dataset.targetKind=kind;const type=row.querySelector('[data-name="line_type"]');type.value=kind;if(kind!=='product'&&kind!=='service')row.querySelector('[data-name="description"]').placeholder=kind==='section'?'Enter a description':'Notes or instructions line...';host.appendChild(row);names(row);Object.entries(data).forEach(([k,v])=>{const el=row.querySelector(`[data-name="${k}"]`);if(el)el.value=v??''});if(kind==='product'){const p=row.querySelector('.line-product');if(data.product_id)p.value=data.product_id;syncLots(row,data.product_lot_id);row.querySelector('.line-description').value=data.description||p.options[p.selectedIndex]?.text||''}if(kind==='service'){if(data.service_id){const srvInput=row.querySelector('[data-name="service_id"]');if(srvInput)srvInput.value=data.service_id}if(data.description){row.querySelector('.line-description').value=data.description}}calc();row.querySelector('select:not([type="hidden"]),input:not([type="hidden"])')?.focus({ preventScroll: true });return row}
 function renderDraftJournal(untaxed,taxTotal,grand,hasProduct){
   const rows=document.getElementById('draft-journal-rows');if(!rows)return;
   rows.replaceChildren();
   if(!hasProduct){const empty=document.createElement('div');empty.className='journal-empty';empty.textContent='Add a product line to preview journal items.';rows.appendChild(empty)}
   else {
     const item=(code,name,label,debit,credit)=>{const row=document.createElement('div');row.className='journal-item';const account=document.createElement('span'),number=document.createElement('b');number.textContent=code;account.append(number,document.createTextNode(' '+name));row.appendChild(account);[label,'—',debit.toFixed(2)+' Rs',credit.toFixed(2)+' Rs','—'].forEach((value,index)=>{const cell=document.createElement('span');cell.textContent=value;if(index===1||index===4)cell.className='journal-muted';row.appendChild(cell)});rows.appendChild(row)};
     item('121000','Account Receivable',document.querySelector('.odoo-number').value,grand,0);
     item('400000','Product Sales','Sales revenue',0,untaxed);
     if(taxTotal>0)item('220000','Tax Payable','Sales tax',0,taxTotal);
   }
   document.getElementById('journal-debit-total').textContent=grand.toFixed(2)+' Rs';
   document.getElementById('journal-credit-total').textContent=(untaxed+taxTotal).toFixed(2)+' Rs';
 }
 function calc(){let untaxed=0,taxTotal=0,grand=0,hasProduct=false;host.querySelectorAll('.odoo-line').forEach(row=>{
   if(row.classList.contains('row-pending')) return;
   const qty=+(row.querySelector('.line-qty').value||0),price=+(row.querySelector('.line-price').value||0),taxSelect=row.querySelector('.line-tax'),rate=+(taxSelect.options[taxSelect.selectedIndex]?.dataset.rate||0);
   const gross=Math.round(qty*price*100)/100,discount=Math.round(gross*(+(row.querySelector('.line-discount')?.value||0))/100*100)/100;
   const raw=Math.round((gross-discount)*100)/100,inclusive=document.getElementById('tax-display').value==='inclusive',base=inclusive&&rate?Math.round(raw/(1+rate/100)*100)/100:raw,tax=inclusive?Math.round((raw-base)*100)/100:Math.round(base*rate)/100,total=base+tax;
   row.querySelector('.line-discount-amount').textContent='LKR '+discount.toFixed(2);
   untaxed+=base;taxTotal+=tax;grand+=total;hasProduct ||= (!!row.querySelector('.line-product')?.value || row.dataset.targetKind === 'service' || row.dataset.kind === 'service' || !!row.querySelector('[data-name="service_id"]')?.value);row.querySelector('.line-amount').textContent=total.toFixed(2)+' Rs'
 });untaxed=Math.round(untaxed*100)/100;taxTotal=Math.round(taxTotal*100)/100;grand=Math.round(grand*100)/100;document.getElementById('untaxed-total').textContent=untaxed.toFixed(2)+' Rs';document.getElementById('tax-total').textContent=taxTotal.toFixed(2)+' Rs';document.getElementById('grand-total').textContent=grand.toFixed(2)+' Rs';if(!invoiceLocked)document.getElementById('amount-due').textContent=grand.toFixed(2)+' Rs';renderDraftJournal(untaxed,taxTotal,grand,hasProduct);updateCatalogTotal()}
 host.addEventListener('input',calc);host.addEventListener('focusin', e => {
    if (e.target.classList.contains('line-product')) {
      e.target.dataset.prevVal = e.target.value;
    }
  });
  host.addEventListener('change', e => {
    if (e.target.classList.contains('line-product')) {
      const select = e.target;
      const prevVal = select.dataset.prevVal;
      const updateProduct = () => {
        const opt = select.options[select.selectedIndex];
        const row = select.closest('.odoo-line');
        row.querySelector('.line-price').value = opt.dataset.price || 0;
        row.querySelector('.line-tax').value = opt.dataset.tax || '';
        row.querySelector('.line-description').value = opt.text;
        syncLots(row);
        calc();
        select.dataset.prevVal = select.value;
      };

      if (prevVal && prevVal !== select.value && window.showInvoiceConfirm) {
        window.showInvoiceConfirm('Are you sure you want to change this record?', () => {
          updateProduct();
        }, () => {
          select.value = prevVal;
        });
      } else {
        updateProduct();
      }
    } else {
      calc();
    }
  });host.addEventListener('click', e => {
    const remove = e.target.closest('.line-remove');
    if (remove) {
      const row = remove.closest('.odoo-line,.odoo-text-line');
      if (window.showInvoiceConfirm) {
        window.showInvoiceConfirm('Are you sure you want to delete this record?', () => {
          row.remove();
          calc();
        });
      } else {
        row.remove();
        calc();
      }
    }
  });
 document.querySelector('[name="location_id"]')?.addEventListener('change',()=>host.querySelectorAll('.odoo-line').forEach(row=>syncLots(row)));
 document.querySelectorAll('[data-add]').forEach(b=>b.addEventListener('click',()=>add(b.dataset.add)));
 document.querySelectorAll('[data-tab]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.querySelectorAll('.odoo-tab-panel').forEach(panel=>panel.classList.toggle('hidden',panel.id!==`tab-${b.dataset.tab}`))}));
 document.querySelectorAll('.tax-switch [data-tax]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.tax-switch [data-tax]').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.getElementById('tax-display').value=b.dataset.tax;calc()}));
 window.invoiceAddRow=add;window.invoiceCalc=calc;
 const invoiceDate=document.querySelector('[name="invoice_date"]'),dueDate=document.getElementById('invoice-due-date'),paymentTerm=document.getElementById('invoice-payment-term'),dueField=document.getElementById('invoice-due-field'),dueLabel=document.getElementById('invoice-due-label'),termDialog=document.getElementById('payment-term-dialog');
 const dateValue=date=>`${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
 const displayDate=date=>date.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
 function paymentSchedule(){
   if(!paymentTerm) return {value:'',base:new Date(),rules:[]};
   const value=(paymentTerm.value||'').trim(),base=new Date(((invoiceDate?.value)||dateValue(new Date()))+'T00:00:00'),rules=[];
   if(!value)return {value,base,rules};
   if(value==='30% Now, Balance 60 Days'){
     const later=new Date(base);later.setDate(later.getDate()+60);
     rules.push({percent:30,date:new Date(base),after:'On invoice date'},{percent:70,date:later,after:'60 Days after invoice date'});
     return {value,base,rules};
   }
   const result=new Date(base);let after='On invoice date';
   if(value==='End of Following Month'){
     result.setFullYear(result.getFullYear(),result.getMonth()+2,0);after='End of following month';
   }else if(value==='10 Days after End of Next Month'){
     result.setFullYear(result.getFullYear(),result.getMonth()+2,0);result.setDate(result.getDate()+10);after='10 Days after end of next month';
   }else {
     const match=value.match(/(\d+)\s*days?/i),days=match?Number(match[1]):0;
     result.setDate(result.getDate()+days);after=days?`${days} Days after invoice date`:'On invoice date';
   }
   rules.push({percent:100,date:result,after});
   return {value,base,rules};
 }
 function syncPaymentTerm(){
   if(!paymentTerm) return;
   const hasTerm=(paymentTerm.value||'').trim().length>0;
   if(dueField) dueField.classList.toggle('has-payment-term',hasTerm);
   if(dueLabel) dueLabel.textContent=hasTerm?'Payment terms':'Due Date';
   if(hasTerm && dueDate){
     const schedule=paymentSchedule();
     if(schedule.rules.length) dueDate.value=dateValue(schedule.rules[schedule.rules.length-1].date);
   }
 }
 function showPaymentTermDetails(){
   const schedule=paymentSchedule();if(!schedule.value || !termDialog)return;
   const pTitle = document.getElementById('payment-term-title'); if (pTitle) pTitle.textContent=schedule.value;
   const pEx = document.getElementById('payment-example-date'); if (pEx) pEx.textContent=displayDate(schedule.base);
   const pSum = document.getElementById('payment-term-summary-name'); if (pSum) pSum.textContent=schedule.value;
   const pFin = document.getElementById('payment-final-date'); if (pFin) pFin.textContent=displayDate(schedule.rules[schedule.rules.length-1].date);
   const rules=document.getElementById('payment-term-rules'),preview=document.getElementById('payment-term-preview');
   if(rules) rules.replaceChildren();
   if(preview) preview.replaceChildren();
   schedule.rules.forEach((rule,index)=>{
     const row=document.createElement('div');row.className='payment-term-rule';
     const due=document.createElement('span'),after=document.createElement('span');
     due.textContent=`${rule.percent.toFixed(2)}%`;after.textContent=rule.after;
     row.append(due,after);
     if(rules) rules.appendChild(row);
     const installment=document.createElement('div');
     installment.innerHTML=`<b>${index+1}${index===0?'st':index===1?'nd':index===2?'rd':'th'} installment</b> &nbsp; ${(1000*rule.percent/100).toFixed(2)} Rs &nbsp;·&nbsp; Due <b>${displayDate(rule.date)}</b>`;
     if(preview) preview.appendChild(installment);
   });
   if(typeof termDialog.showModal === 'function') termDialog.showModal();
 }
 paymentTerm?.addEventListener('input',syncPaymentTerm);
 paymentTerm?.addEventListener('change',syncPaymentTerm);
 invoiceDate?.addEventListener('change',()=>{if(paymentTerm?.value.trim())syncPaymentTerm()});
 document.getElementById('open-payment-term')?.addEventListener('click',showPaymentTermDetails);
 termDialog?.addEventListener('click',event=>{if(event.target===termDialog && typeof termDialog.close === 'function')termDialog.close()});
 syncPaymentTerm();
 const paymentDialog=document.getElementById('invoice-payment-dialog');if(paymentDialog){document.getElementById('open-payment')?.addEventListener('click',()=>paymentDialog.showModal());paymentDialog.querySelectorAll('[data-close-payment]').forEach(button=>button.addEventListener('click',()=>paymentDialog.close()));paymentDialog.addEventListener('click',event=>{if(event.target===paymentDialog)paymentDialog.close()})}
 const ordersDialog=document.getElementById('orders-dialog');document.getElementById('open-orders')?.addEventListener('click',()=>ordersDialog?.showModal());ordersDialog?.querySelector('[data-close-orders]')?.addEventListener('click',()=>ordersDialog.close());

 const conflictDialog=document.getElementById('order-conflict-dialog');
 const conflictOrderNumber=document.getElementById('conflict-order-number');
 const btnConflictClearAdd=document.getElementById('btn-conflict-clear-add');
 const btnConflictCancel=document.getElementById('btn-conflict-cancel');
 let pendingOrderUrl='';

 ordersDialog?.addEventListener('click', (e) => {
   const selectBtn = e.target.closest('a[href*="order="]');
   if (!selectBtn) return;

   const clickedOrderId = selectBtn.dataset.orderId || (selectBtn.href.match(/order=(\d+)/)?.[1]);
   const clickedOrderNumber = selectBtn.dataset.orderNumber || selectBtn.closest('tr')?.querySelector('td')?.textContent?.trim() || '';
   const targetUrl = selectBtn.href;

   const currentOrderId = document.querySelector('[name="sales_order_id"]')?.value;
   const currentOrderBadge = document.getElementById('ref-order-selected-badge');
   const customerId = document.querySelector('[name="customer_id"]')?.value;

   let hasNonEmptyLines = false;
   host.querySelectorAll('.odoo-line, .odoo-text-line').forEach(row => {
     const prod = row.querySelector('.line-product')?.value;
     const srv = row.querySelector('[data-name="service_id"]')?.value;
     const desc = row.querySelector('.line-description')?.value;
     const price = parseFloat(row.querySelector('.line-price')?.value || 0) || 0;
     if (prod || srv || (desc && desc.trim()) || price > 0) {
       hasNonEmptyLines = true;
     }
   });

   const hasExistingData = !!(currentOrderId || currentOrderBadge || (customerId && hasNonEmptyLines));

   if (hasExistingData && String(currentOrderId) !== String(clickedOrderId)) {
     e.preventDefault();
     e.stopPropagation();

     pendingOrderUrl = targetUrl;
     if (conflictOrderNumber) conflictOrderNumber.textContent = clickedOrderNumber;

     if (typeof ordersDialog.close === 'function') ordersDialog.close();
     if (conflictDialog) {
       if (typeof conflictDialog.showModal === 'function') conflictDialog.showModal();
       else conflictDialog.setAttribute('open', '');
     }
   }
 });

 btnConflictClearAdd?.addEventListener('click', () => {
   if (pendingOrderUrl) {
     window.location.href = pendingOrderUrl;
   }
 });

 btnConflictCancel?.addEventListener('click', () => {
   if (conflictDialog) {
     if (typeof conflictDialog.close === 'function') conflictDialog.close();
     else conflictDialog.removeAttribute('open');
   }
 });

 conflictDialog?.addEventListener('click', (e) => {
   if (e.target === conflictDialog) {
     if (typeof conflictDialog.close === 'function') conflictDialog.close();
     else conflictDialog.removeAttribute('open');
   }
 });
 const workspace=document.getElementById('invoice-workspace'),preview=document.getElementById('invoice-preview-panel');document.getElementById('open-preview')?.addEventListener('click',()=>{preview.classList.remove('hidden');workspace.classList.add('preview-open');const frame=preview.querySelector('iframe');if(frame&&!frame.src)frame.src=frame.dataset.src});document.getElementById('close-preview')?.addEventListener('click',()=>{preview.classList.add('hidden');workspace.classList.remove('preview-open')});
 const sendDialog=document.getElementById('invoice-send-dialog');const openSend=()=>sendDialog?.showModal();document.getElementById('open-send')?.addEventListener('click',openSend);document.querySelector('.chatter-send')?.addEventListener('click',openSend);
 const activityDialog=document.getElementById('invoice-activity-dialog');function openActivity(type){if(!activityDialog)return;activityDialog.querySelector('[name="type"]').value=type;activityDialog.querySelector('#invoice-activity-title').textContent=type==='note'?'Log note':'Record activity';activityDialog.querySelector('[name="message"]').placeholder=type==='note'?'Write a note about this invoice':'Describe the activity or follow-up';activityDialog.showModal()}document.querySelector('.chatter-note')?.addEventListener('click',()=>openActivity('note'));document.querySelector('.chatter-activity')?.addEventListener('click',()=>openActivity('activity'));
 const creditDialog=document.getElementById('invoice-credit-note-dialog');document.getElementById('open-credit-note')?.addEventListener('click',()=>creditDialog?.showModal());
 [sendDialog,activityDialog,creditDialog].filter(Boolean).forEach(dialog=>{dialog.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>dialog.close()));dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close()})});
 if(sendDialog){const sendForm=sendDialog.querySelector('form');sendForm.addEventListener('submit',event=>{const email=sendForm.querySelector('[name="by_email"]'),post=sendForm.querySelector('[name="by_post"]'),download=sendForm.querySelector('[name="download"]');if(!email.checked&&!post.checked&&!download.checked){event.preventDefault();alert('Choose at least one delivery method.');return}if(download.checked){const printable=@json($invoice->exists ? route('invoices.pdf',$invoice) : null);if(printable)window.open(printable,'_blank','noopener')} });}
 const catalog=document.getElementById('catalog-workspace'),closeCatalog=()=>catalog.classList.add('hidden');document.getElementById('open-catalog').addEventListener('click',()=>catalog.classList.remove('hidden'));document.getElementById('back-to-invoice').addEventListener('click',closeCatalog);document.getElementById('catalog-invoice-breadcrumb')?.addEventListener('click',closeCatalog);
 let selectedCategory='';function filterCatalog(){const query=document.getElementById('catalog-search').value.trim().toLowerCase();let visible=0;document.querySelectorAll('#catalog-products article').forEach(card=>{const matched=card.dataset.search.includes(query)&&(!selectedCategory||card.dataset.category===selectedCategory||card.dataset.rootCategory===selectedCategory);card.classList.toggle('hidden',!matched);if(matched)visible++});document.getElementById('catalog-filter-empty')?.classList.toggle('hidden',visible>0)}document.getElementById('catalog-search').addEventListener('input',filterCatalog);document.querySelectorAll('[data-catalog-category]').forEach(button=>button.addEventListener('click',()=>{selectedCategory=button.dataset.catalogCategory;document.querySelectorAll('[data-catalog-category]').forEach(item=>item.classList.toggle('active',item===button));filterCatalog()}));document.querySelectorAll('.catalog-product-image img').forEach(img=>img.addEventListener('error',()=>{img.classList.add('hidden');img.nextElementSibling?.classList.remove('hidden')}));
 document.querySelectorAll('.catalog-plus').forEach(button=>button.addEventListener('click',()=>{let row=[...host.querySelectorAll('.odoo-line')].find(x=>x.querySelector('.line-product').value===button.dataset.product);if(!row)row=add('product',{product_id:button.dataset.product,description:button.dataset.name,quantity:0,unit_price:button.dataset.price,tax_rate_id:button.dataset.tax});const qty=row.querySelector('.line-qty');qty.value=+(qty.value||0)+1;document.querySelector(`[data-count="${button.dataset.product}"]`).value=qty.value;calc()}));
 document.querySelectorAll('.catalog-minus').forEach(button=>button.addEventListener('click',()=>{const row=[...host.querySelectorAll('.odoo-line')].find(x=>x.querySelector('.line-product').value===button.dataset.product);if(!row)return;const qty=row.querySelector('.line-qty'),next=Math.max(0,+(qty.value||0)-1);if(next===0)row.remove();else qty.value=next;document.querySelector(`[data-count="${button.dataset.product}"]`).value=next;calc()}));
 function updateCatalogTotal(){const selected=document.getElementById('catalog-selected');let total=0,items=[];host.querySelectorAll('.odoo-line').forEach(row=>{const product=row.querySelector('.line-product'),qty=+(row.querySelector('.line-qty').value||0),price=+(row.querySelector('.line-price').value||0),amount=qty*price;total+=amount;if(product.value&&qty>0)items.push({name:product.options[product.selectedIndex].text,qty,amount})});selected.replaceChildren();if(!items.length){const empty=document.createElement('p');empty.textContent='No products selected';selected.appendChild(empty)}else items.forEach(item=>{const line=document.createElement('div');line.className='catalog-selected-row';const name=document.createElement('strong'),meta=document.createElement('span'),amount=document.createElement('b');name.textContent=item.name;meta.textContent=`${item.qty} selected`;amount.textContent=item.amount.toFixed(2)+' Rs';line.append(name,meta,amount);selected.appendChild(line)});document.getElementById('catalog-total').textContent=total.toFixed(2)+' Rs';document.getElementById('catalog-section-total').textContent=total.toFixed(2)+' Rs'}
 saved.forEach(l=>add(l.line_type||'product',l));calc();window.invoiceAddRow=add;window.invoiceCalc=calc;
 if(invoiceLocked){
   document.querySelectorAll('#invoice-form input:not(.odoo-number),#invoice-form select,#invoice-form textarea').forEach(control=>control.disabled=true);
   document.querySelectorAll('#invoice-form .tax-switch [data-tax],#invoice-form [data-add],#invoice-form .line-remove,#open-catalog,#open-payment-term').forEach(button=>button.disabled=true);
 }
};
if (window.AppPage) window.AppPage.ready(initializeInvoiceForm);
else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeInvoiceForm, {once:true});
else initializeInvoiceForm();
})();
</script>
<link rel="stylesheet" href="{{ asset('css/invoice-reference.css') }}?v={{ filemtime(public_path('css/invoice-reference.css')) }}">
@php
    $referenceProducts = $products->mapWithKeys(function ($product) use ($availableSerialStock) {
        $stocks = $product->variants->isNotEmpty()
            ? $product->variants->flatMap->locationStocks->map(fn ($stock) => ['location_id'=>$stock->location_id, 'available'=>(float)$stock->opening_quantity])
            : $product->locations->map(fn ($location) => ['location_id'=>$location->id, 'available'=>(float)$location->pivot->opening_quantity]);
        if ($product->enable_serial) $stocks = $availableSerialStock[$product->id] ?? collect();
        return [$product->id => [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'sku_key' => $product->sku_key ?: ($product->code ?: '12121212'),
            'unit' => $product->unit?->name ?: ($product->unit?->short_name ?: 'Nos'),
            'alert_quantity' => $product->alert_quantity ?? 1,
            'stock' => (bool)$product->manage_stock,
            'lots' => (bool)$product->track_lots,
            'quantities' => $stocks->values(),
            'purchase_price' => (float)$product->purchase_price,
            'selling_price' => (float)$product->selling_price,
            'our_price' => (float)($product->our_price ?: $product->selling_price),
            'product_type' => $product->product_type ?: 'Stock Item',
            'properties' => 'Standard',
            'stock_ledger' => 'Mashi Distributors Advance Creditor Ledger',
            'cogs_ledger' => 'VAT Payable',
            'revenue_ledger' => 'Sales',
        ]];
    });

    $allProductSerials = \Illuminate\Support\Facades\DB::table('product_serial_numbers')
        ->leftJoin('product_variants', 'product_variants.id', '=', 'product_serial_numbers.product_variant_id')
        ->where('product_serial_numbers.status', 'available')
        ->selectRaw('product_serial_numbers.id, COALESCE(product_serial_numbers.product_id, product_variants.product_id) as product_id, product_serial_numbers.location_id, product_serial_numbers.serial_number, product_serial_numbers.status, product_serial_numbers.product_lot_id')
        ->orderBy('product_serial_numbers.id')
        ->get()
        ->groupBy('product_id');

    $referenceServices = ($services ?? \App\Models\Service::where('is_active', true)->orderBy('name')->get())->map(function ($s) {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'code' => $s->code,
            'unit_rate' => (float)$s->unit_rate,
            'description' => $s->description ?? '',
        ];
    });
@endphp
<script type="application/json" id="reference-product-metadata">@json($referenceProducts)</script>
<script type="application/json" id="reference-service-metadata">@json($referenceServices)</script>
<script type="application/json" id="reference-lot-metadata">@json($availableLots)</script>
<script type="application/json" id="reference-serial-metadata">@json($allProductSerials)</script>
<script src="{{ asset('js/invoice-reference.js') }}?v={{ filemtime(public_path('js/invoice-reference.js')) }}"></script>
@endpush
