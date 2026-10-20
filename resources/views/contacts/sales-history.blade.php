@extends('layouts.app')
@section('title','Customer Sales History')
@section('subtitle','Invoices and sales for '.$contact->name)
@section('content')
<section class="rounded-2xl border border-purple-100 bg-white p-6 shadow-sm"><a class="mb-4 inline-block text-sm font-bold text-purple-700" href="{{ route('contacts.index','customer') }}">Back to customers</a><h2 class="text-lg font-bold">{{ $contact->name }}</h2>@if($sales->isEmpty())<p class="mt-4 text-sm text-slate-500">No confirmed invoices for this customer.</p>@endif</section>
@if($sales->isNotEmpty())
<div class="mt-4 overflow-x-auto rounded-xl border border-purple-100 bg-white p-4"><table class="w-full text-left text-sm"><thead><tr><th>Date</th><th>Invoice</th><th>Status</th><th>Total</th><th>Paid</th><th>Credited</th><th>Amount due</th></tr></thead><tbody>
@foreach($sales as $sale)<tr><td>{{ $sale->invoice_date->format('Y-m-d') }}</td><td>{{ $sale->invoice_number }}</td><td>{{ ucfirst($sale->status) }}</td><td>Rs. {{ number_format($sale->total_amount,2) }}</td><td>Rs. {{ number_format($sale->paid_amount,2) }}</td><td>Rs. {{ number_format($sale->credited_amount,2) }}</td><td>Rs. {{ number_format($sale->balance_due,2) }}</td></tr>@endforeach
</tbody><tfoot><tr><td colspan="3">Total</td><td>Rs. {{ number_format($sales->sum('total_amount'),2) }}</td><td>Rs. {{ number_format($sales->sum('paid_amount'),2) }}</td><td>Rs. {{ number_format($sales->sum('credited_amount'),2) }}</td><td>Rs. {{ number_format($sales->sum('balance_due'),2) }}</td></tr></tfoot></table></div>
@endif
@endsection
