@extends('layouts.app')
@section('title','Account Book')
@section('content')
@php
    $openingDate = \Carbon\Carbon::parse($account->opening_balance_at ?? $account->created_at);
    $debit = max(0, (float) $account->opening_balance);
    $credit = max(0, -(float) $account->opening_balance);
@endphp
<h1 class="mb-5 text-xl font-bold">Account Book</h1>
<div id="book-message" role="status" class="mb-4 text-sm"></div>
<div class="mb-6 grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-purple-100 bg-white p-6">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <dt class="font-bold">Account Name:</dt><dd>{{ $account->name }}</dd>
            <dt class="font-bold">Account Type:</dt><dd>{{ $account->type?->name ?? '—' }} @if($account->subType) / {{ $account->subType->name }} @endif</dd>
            <dt class="font-bold">Account Number:</dt><dd class="break-all">{{ $account->account_number }}</dd>
            <dt class="font-bold">Balance:</dt><dd id="book-balance">Rs. {{ number_format($account->current_balance,2) }}</dd>
        </dl>
    </section>
    <section class="rounded-2xl border border-purple-100 bg-white p-6 lg:col-span-2">
        <h2 class="mb-6 font-bold"><i class="bi bi-funnel-fill"></i> Filters</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <label class="text-sm font-bold">Date Range: From<input id="date-from" type="date" class="mt-2 block w-full rounded-xl border p-2"></label>
            <label class="text-sm font-bold">To<input id="date-to" type="date" class="mt-2 block w-full rounded-xl border p-2"></label>
            <label class="text-sm font-bold">Transaction Type:<select id="transaction-type" class="mt-2 block w-full rounded-xl border p-2"><option value="">All</option><option value="debit">Debit</option><option value="credit">Credit</option></select></label>
        </div>
        <p id="filter-error" class="mt-2 text-sm text-rose-600" role="alert"></p>
    </section>
</div>
<section class="rounded-2xl border border-purple-100 bg-white p-6">
    <table id="book-table" class="w-full text-left">
        <thead><tr><th>Date</th><th>Description</th><th>Payment Method</th><th>Payment details</th><th>Note</th><th>Added By</th><th>Debit</th><th>Credit</th><th>Balance</th><th>Action</th></tr></thead>
        <tbody>@foreach($entries as $entry)
        <tr data-date="{{ $entry['date']->format('Y-m-d') }}" data-debit="{{ $entry['debit']/100 }}" data-credit="{{ $entry['credit']/100 }}">
            <td>{{ $entry['date']->format('Y-m-d H:i') }}</td><td>{{ $entry['description'] }}</td>
            <td>{{ $entry['transfer'] ? 'Account transfer' : '' }}</td>
            <td>@if($entry['transfer']?->document_path)<a class="text-purple-600 underline" href="{{ route('payment-accounts.transfers.document',$entry['transfer']) }}">Download document</a>@endif</td>
            <td>{{ $entry['note'] }}</td><td>{{ $entry['creator'] }}</td>
            <td>{{ number_format($entry['debit']/100,2) }}</td><td>{{ number_format($entry['credit']/100,2) }}</td><td>{{ number_format($entry['balance']/100,2) }}</td>
            <td>@unless($entry['transfer'])<button id="edit-opening" class="rounded-lg bg-purple-600 px-3 py-1.5 text-xs font-bold text-white">Edit</button>@endunless</td>
        </tr>@endforeach</tbody>
        <tfoot class="bg-purple-50 font-bold"><tr><td>Total:</td><td></td><td></td><td></td><td></td><td></td><td id="total-debit"></td><td id="total-credit"></td><td></td><td></td></tr></tfoot>
    </table>
</section>
<dialog id="opening-dialog" class="m-auto w-full max-w-xl rounded-2xl p-0 shadow-xl">
    <form id="opening-form" action="{{ route('payment-accounts.opening-balance',$account) }}" method="POST">
        @csrf @method('PUT')
        <header class="flex justify-between border-b bg-purple-50 p-5 font-bold text-purple-900">Edit Opening Balance<button type="button" data-close aria-label="Close">×</button></header>
        <div class="space-y-4 p-5">
            <p class="text-sm"><b>Selected Account:</b> {{ $account->name }}</p>
            <label class="block text-sm font-bold">Amount:*<input name="amount" required type="number" step="0.01" value="{{ $account->opening_balance }}" class="mt-2 w-full rounded-xl border p-3"></label>
            <label class="block text-sm font-bold">Date:*<input name="date" required type="datetime-local" value="{{ $openingDate->format('Y-m-d\TH:i') }}" class="mt-2 w-full rounded-xl border p-3"></label>
            <p id="opening-error" class="text-sm text-rose-600" role="alert"></p>
        </div>
        <footer class="flex justify-end gap-2 border-t p-5"><button type="submit" class="rounded-xl bg-purple-600 px-4 py-2 text-sm font-bold text-white">Submit</button><button type="button" data-close class="rounded-xl bg-slate-100 px-4 py-2 text-sm">Close</button></footer>
    </form>
</dialog>
@endsection
@push('scripts')
<style>#opening-dialog::backdrop{background:rgb(15 23 42 / .55)}</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('opening-dialog');
    const form = document.getElementById('opening-form');
    const from = document.getElementById('date-from'), to = document.getElementById('date-to');
    const kind = document.getElementById('transaction-type');
    $.fn.dataTable.ext.search.push((settings, data, index) => {
        if (settings.nTable.id !== 'book-table') return true;
        const row = settings.aoData[index].nTr.dataset;
        return (!from.value || row.date >= from.value) && (!to.value || row.date <= to.value)
            && (!kind.value || Number(row[kind.value]) > 0);
    });
    const exports = {columns: [0,1,2,3,4,5,6,7,8], modifier: {search:'applied'}};
    const table = $('#book-table').DataTable({
        pageLength:25, scrollX:true, order:[[0,'asc']], columnDefs:[{targets:9,orderable:false,searchable:false}],
        dom:'lBfrtip',
        buttons:[
            {extend:'csvHtml5',text:'Export to CSV',exportOptions:exports,footer:true},
            {extend:'excelHtml5',text:'Export to Excel',exportOptions:exports,footer:true},
            {extend:'print',text:'Print',exportOptions:exports,footer:true},
            {extend:'colvis',text:'Column visibility'},
            {extend:'pdfHtml5',text:'Export to PDF',orientation:'landscape',exportOptions:exports,footer:true}
        ],
        footerCallback() {
            let debit=0,credit=0;
            this.api().rows({search:'applied'}).nodes().each(row => {debit+=Number(row.dataset.debit);credit+=Number(row.dataset.credit)});
            document.getElementById('total-debit').textContent='Rs. '+debit.toFixed(2);
            document.getElementById('total-credit').textContent='Rs. '+credit.toFixed(2);
        }
    });
    [from,to,kind].forEach(input => input.addEventListener('change', () => {
        document.getElementById('filter-error').textContent=from.value && to.value && from.value>to.value?'From date must be before To date.':'';
        table.draw();
    }));
    document.addEventListener('click', event => {
        if(event.target.closest('#edit-opening')) dialog.showModal();
        if(event.target.closest('[data-close]')) dialog.close();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button=form.querySelector('[type=submit]');
        button.disabled=true;
        document.getElementById('opening-error').textContent='';
        try {
            const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(form)});
            const result=await response.json();
            if(!response.ok) throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message);
            const page=await fetch(location.href);
            if(!page.ok) throw new Error('Saved, but could not refresh the account book.');
            const doc=new DOMParser().parseFromString(await page.text(),'text/html');
            table.clear();
            table.rows.add(Array.from(doc.querySelectorAll('#book-table tbody tr'))).draw();
            document.getElementById('book-balance').textContent=doc.getElementById('book-balance').textContent;
            dialog.close();
            document.getElementById('book-message').textContent=result.message;
        } catch(error) { document.getElementById('opening-error').textContent=error.message; }
        finally {button.disabled=false}
    });
});
</script>
@endpush
