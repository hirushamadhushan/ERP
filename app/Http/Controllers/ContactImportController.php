<?php

namespace App\Http\Controllers;

use App\Services\ContactCsvImport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContactImportController extends Controller
{
    public function index()
    {
        return view('contacts.import', ['columns' => ContactCsvImport::COLUMNS]);
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ContactCsvImport::COLUMNS, ',', '"', '');
            fclose($stream);
        }, 'contacts-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, ContactCsvImport $importer)
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt', 'extensions:csv']]);
        try {
            $count = $importer->import($request->file('file')->getRealPath());
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['file' => 'A Contact ID already exists. No rows were imported. Check the IDs and try again.']);
        }

        return redirect()->route('contacts.import.index')->with('success', "Imported {$count} contacts successfully.");
    }
}
