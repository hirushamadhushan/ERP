@extends('layouts.app')
@section('title','Sales')
@section('content')
<link rel="stylesheet" href="{{ asset('css/sales-dashboard.css') }}?v={{ filemtime(public_path('css/sales-dashboard.css')) }}">
<div class="sales-dashboard">
    <div class="sales-breadcrumb"><h1>Transactions</h1><span>Sales <b>›</b> Transactions</span></div>
    @if(session('success'))<div class="sales-notice" role="status">{{ session('success') }}</div>@endif
    <section class="sales-dashboard-panel" aria-label="Sales transactions">
        <div class="sales-actions-wrap"><div class="sales-actions">
            <article class="sales-action-card">
                <span class="sales-card-icon"><i class="bi bi-cart" aria-hidden="true"></i></span>
                <h2>Order</h2><p>Create orders and convert them into customer invoices.</p>
                @if(auth()->user()->canUse('invoice.create'))
                <a class="sales-primary" href="{{ route('sales.create') }}"><i class="bi bi-plus" aria-hidden="true"></i> Add New</a>
                @else<span class="sales-unavailable">View orders below</span>@endif
            </article>
            <article class="sales-action-card">
                <span class="sales-card-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                <h2>Invoice</h2><p>Generate bills and manage customer payments.</p>
                @if(auth()->user()->canUse('invoice.create'))
                <a class="sales-primary" href="{{ route('invoices.create') }}"><i class="bi bi-plus" aria-hidden="true"></i> Add New</a>
                @else<a class="sales-primary" href="{{ route('invoices.index') }}">View</a>@endif
                <a class="sales-list-link" href="{{ route('invoices.index') }}">View all invoices</a>
            </article>
            <article class="sales-action-card">
                <span class="sales-card-icon"><i class="bi bi-truck" aria-hidden="true"></i></span>
                <h2>Dispatch</h2><p>Track and deliver products to customers.</p>
                @if(auth()->user()->canUse('delivery.view'))
                <a class="sales-primary" href="{{ route('delivery.consignments.index') }}"><i class="bi bi-eye" aria-hidden="true"></i> View</a>
                @else<span class="sales-unavailable">Access restricted</span>@endif
            </article>
        </div></div>
        <section class="sales-recents">
            <h2>Recent Sales</h2>
            <div class="sales-table-scroll"><table>
                <thead><tr><th>Customer</th><th>Sale By</th><th>Sales Order</th><th>Invoice</th><th>Dispatch</th><th>Status</th></tr></thead>
                <tbody>@forelse($recentSales as $sale)
                <tr>
                    <td>{{ $sale['customer'] ?: '—' }}</td><td>{{ $sale['salesperson'] ?: '—' }}</td>
                    <td>@if($sale['order'])
                        @if(!$sale['invoice'] && $sale['order']->status==='open' && auth()->user()->canUse('invoice.create'))
                        <a href="{{ route('invoices.create',['order'=>$sale['order']->id]) }}">{{ $sale['order']->order_number }} | {{ $sale['order']->order_date->format('Y-m-d') }}</a>
                        @else{{ $sale['order']->order_number }} | {{ $sale['order']->order_date->format('Y-m-d') }}@endif
                    @else — @endif</td>
                    <td>@if($sale['invoice'])<a href="{{ route('invoices.edit',$sale['invoice']) }}">{{ $sale['invoice']->invoice_number }} | {{ $sale['invoice']->invoice_date->format('Y-m-d') }}</a>@else — @endif</td>
                    <td>—</td>
                    <td><span class="sales-status {{ $sale['status']==='Success'?'is-success':($sale['status']==='Follow Up'?'is-follow-up':'is-draft') }}"><i aria-hidden="true"></i>{{ $sale['status'] }}</span></td>
                </tr>
                @empty<tr><td colspan="6" class="sales-empty">No sales yet. Add an order or invoice to get started.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    </section>
</div>
@endsection
