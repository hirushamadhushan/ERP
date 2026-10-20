<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportContactsRequest;
use App\Services\ContactCsvImport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class ContactImportController
 *
 * Coordinates batch CSV onboarding of customers, suppliers, and contacts,
 * ensuring schema adherence and preventing duplicate business identifiers.
 */
class ContactImportController extends Controller
{
    /**
     * Display the contact bulk import view with CSV specifications.
     *
     * @return View
     */
    public function index(): View
    {
        return view('contacts.import', [
            'columns' => ContactCsvImport::COLUMNS,
        ]);
    }

    /**
     * Download the pre-formatted CSV template file with UTF-8 BOM encoding.
     *
     * @return StreamedResponse
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ContactCsvImport::COLUMNS, ',', '"', '');
            fclose($stream);
        }, 'contacts-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Parse and import contact records from an uploaded CSV spreadsheet.
     *
     * @param ImportContactsRequest $request
     * @param ContactCsvImport $importer
     * @return RedirectResponse
     */
    public function store(ImportContactsRequest $request, ContactCsvImport $importer): RedirectResponse
    {
        try {
            $count = $importer->import($request->file('file')->getRealPath());

            return redirect()->route('contacts.import.index')->with('success', "Imported {$count} contacts successfully.");
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'file' => 'A Contact ID already exists. No rows were imported. Check the IDs and try again.',
            ]);
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to import contacts from CSV', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Contact import failed: ' . $e->getMessage());
        }
    }
}
