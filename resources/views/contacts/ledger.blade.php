@extends('layouts.app')
@section('title','Customer Ledger')
@section('subtitle','Statement for '.$contact->name)
@section('content')
<section class="rounded-2xl border border-purple-100 bg-white p-6 shadow-sm"><div class="mb-5 flex items-center justify-between"><div><h2 class="text-lg font-bold">{{ $contact->name }}</h2><p class="text-sm text-slate-500">{{ $contact->contact_id }} · Current due: Rs. {{ number_format($contact->due_balance,2) }} · Reward points: {{ $contact->reward_points }}</p></div><a class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold" href="{{ route('contacts.index','customer') }}">Back to customers</a></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-purple-50"><tr><th class="p-3">Date</th><th>Reference</th><th>Details</th><th>Received</th><th>Collected by</th></tr></thead><tbody><tr class="border-b"><td class="p-3">{{ $contact->created_at->format('Y-m-d') }}</td><td>Opening</td><td>Opening balance</td><td>—</td><td>—</td></tr>@forelse($payments as $payment)<tr class="border-b"><td class="p-3">{{ $payment->paid_at->format('Y-m-d H:i') }}</td><td>{{ $payment->receipt_no }}</td><td>{{ $payment->payment_method }} {{ $payment->note ? '· '.$payment->note : '' }}</td><td>Rs. {{ number_format($payment->amount,2) }}</td><td>{{ $payment->creator?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">No due payments recorded.</td></tr>@endforelse</tbody></table></div></section>
<section class="mt-4 rounded-2xl border border-purple-100 bg-white p-6">
<h3 class="mb-3 font-bold">Invoice transactions</h3>
<p class="mb-3 text-sm text-slate-500">Pay invoice balances from Invoices. Contacts Pay collects opening balances only.</p>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Debit</th><th>Credit</th><th>Invoice due</th></tr></thead><tbody>
@forelse($invoices as $invoice)
<tr><td>{{ $invoice->invoice_date->format('Y-m-d') }}</td><td>{{ $invoice->invoice_number }}</td><td>Invoice</td><td>{{ number_format($invoice->total_amount,2) }}</td><td>—</td><td>{{ number_format($invoice->balance_due,2) }}</td></tr>
@foreach($invoice->payments as $payment)<tr><td>{{ $payment->payment_date->format('Y-m-d') }}</td><td>{{ $payment->payment_number }}</td><td>Payment</td><td>—</td><td>{{ number_format($payment->amount,2) }}</td><td>—</td></tr>@endforeach
@foreach($invoice->creditNotes as $note)<tr><td>{{ $note->credit_date?->format('Y-m-d') }}</td><td>{{ $note->credit_number }}</td><td>Credit note</td><td>—</td><td>{{ number_format($note->amount,2) }}</td><td>—</td></tr>@endforeach
@empty<tr><td colspan="6" class="p-4 text-slate-500">No confirmed invoices.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
