@extends('layouts.app')
@section('title','Customer Sales History')
@section('subtitle','Invoices and sales for '.$contact->name)
@section('content')
<section class="rounded-2xl border border-purple-100 bg-white p-6 shadow-sm"><a class="mb-4 inline-block text-sm font-bold text-purple-700" href="{{ route('contacts.index','customer') }}">← Back to customers</a><h2 class="text-lg font-bold">{{ $contact->name }}</h2><p class="mt-4 rounded-xl bg-slate-50 p-5 text-sm text-slate-600">No sales are recorded yet. Sales created by the POS/Sales module will appear here automatically.</p></section>
@endsection
