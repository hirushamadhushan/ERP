@extends('layouts.app')
@section('title', 'Import Contacts')
@section('subtitle', 'Import customers and suppliers')
@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div role="status" class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('success') }} <a href="{{ route('contacts.index', 'customer') }}" class="font-bold underline">View Customers</a> · <a href="{{ route('contacts.index', 'supplier') }}" class="font-bold underline">View Suppliers</a></div>
    @endif
    @if($errors->any())
        <div role="alert" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            <p class="font-bold mb-2">Import failed. No contacts were added.</p>
            <ul class="list-disc pl-5 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            <p class="mt-2">Correct the file and select it again. Up to 50 errors are shown at a time.</p>
        </div>
    @endif
    <section class="bg-white rounded-2xl border border-purple-100 shadow-sm p-5 sm:p-6">
        <h1 class="text-lg font-bold text-slate-900 mb-5">Import Contacts</h1>
        <form id="import-contacts-form" method="POST" action="{{ route('contacts.import.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="w-full sm:max-w-md min-w-0">
                    <label for="import-file" class="block text-xs font-bold text-slate-700 mb-2">File To Import <span class="text-rose-500">*</span></label>
                    <input id="import-file" name="file" type="file" accept=".csv,text/csv" required aria-describedby="import-file-help" class="block w-full min-w-0 text-sm text-slate-500 border border-slate-300 rounded-xl file:mr-3 file:border-0 file:bg-purple-50 file:px-4 file:py-2.5 file:text-purple-700 file:font-semibold focus:outline-none focus:ring-2 focus:ring-purple-400">
                </div>
                <button id="import-submit" type="submit" class="inline-flex justify-center items-center gap-2 px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-500/20"><i class="bi bi-upload" aria-hidden="true"></i><span>Submit</span></button>
            </div>
            <p id="import-file-help" class="text-xs text-slate-500 mt-2">UTF-8 CSV only · Maximum 2 MB · Up to 1,000 contacts per file</p>
        </form>
        <a href="{{ route('contacts.import.template') }}" class="inline-flex items-center gap-2 mt-6 px-4 py-2.5 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold border border-purple-100"><i class="bi bi-download" aria-hidden="true"></i>Download template file</a>
    </section>
    <section class="bg-white rounded-2xl border border-purple-100 shadow-sm p-5 sm:p-6">
        <h2 class="text-lg font-bold text-slate-900 mb-5">Instructions</h2>
        <p class="text-sm font-bold text-slate-700">Follow the instructions carefully before importing the file.</p>
        <p class="text-sm text-slate-500 mt-1">Keep the template header and the following column order. Enter contacts below the header; leave optional cells blank.</p>
        <ul class="list-disc pl-5 text-xs text-slate-500 space-y-1 mt-3 mb-5">
            <li>Existing Contact IDs are rejected; imports never overwrite existing contacts.</li>
            <li>Keep phone numbers, postal codes and Contact IDs as text to preserve leading zeros and + signs.</li>
            <li>Use plain decimal amounts without currency symbols or thousands separators.</li>
            <li>Both creates one shared contact that appears in Customers and Suppliers.</li>
            <li>Prefix, first, middle and last names are combined into the contact Name.</li>
            <li>If any row is invalid, nothing is imported. Row numbers count CSV records, including the header.</li>
        </ul>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse min-w-[560px]">
                <thead class="bg-slate-50 text-slate-700 text-xs"><tr><th class="p-3 border-b border-slate-200">Column Number</th><th class="p-3 border-b border-slate-200">Column Name</th><th class="p-3 border-b border-slate-200">Instruction</th></tr></thead>
                <tbody>
                @foreach($columns as $column)
                    @php
                        $required = in_array($column, ['Contact type', 'First Name', 'Mobile']);
                        $supplierRequired = in_array($column, ['Business Name', 'Pay term', 'Pay term period']);
                    @endphp
                    <tr class="odd:bg-white even:bg-slate-50/60">
                        <td class="p-3 border-b border-slate-100 align-top text-slate-500">{{ $loop->iteration }}</td>
                        <td class="p-3 border-b border-slate-100 align-top text-slate-700">{{ $column }} <span class="block text-xs {{ $required ? 'text-purple-600' : 'text-slate-400' }}">{{ $required ? '(Required)' : ($supplierRequired ? '(Required for supplier or both)' : '(Optional)') }}</span></td>
                        <td class="p-3 border-b border-slate-100 align-top text-slate-600">
                            @switch($column)
                                @case('Contact type') Available options:<br><strong>1 = Customer<br>2 = Supplier<br>3 = Both</strong> @break
                                @case('Contact ID') Leave blank to automatically generate a unique Contact ID. @break
                                @case('Pay term') Whole number, 0–100000. Supply a period when entering a pay term. @break
                                @case('Pay term period') Available options: <strong>days</strong> and <strong>months</strong>. @break
                                @case('Opening Balance') Non-negative amount, up to two decimal places. Blank means 0. @break
                                @case('Credit Limit') Non-negative amount. Leave blank for no limit. @break
                                @case('Date of birth') Format <strong>Y-m-d</strong> (e.g. 2000-09-11). Cannot be a future date. @break
                                @case('Email') Enter a valid email address. @break
                                @default
                            @endswitch
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('import-contacts-form').addEventListener('submit', () => {
    const button = document.getElementById('import-submit');
    button.disabled = true;
    button.querySelector('span').textContent = 'Importing…';
});
</script>
@endpush
