<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteSerialNumbersRequest;
use App\Http\Requests\GenerateSerialNumbersRequest;
use App\Http\Requests\ImportSerialNumbersRequest;
use App\Http\Requests\SerialFilterRequest;
use App\Http\Requests\SerialReferenceRequest;
use App\Http\Requests\StoreSerialPreviewRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductSerialNumber;
use App\Services\SerialNumberSpreadsheet;
use App\Services\SerialNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class SerialNumberController
 *
 * Manages item-level serialized inventory tracking, algorithmic batch generation,
 * spreadsheet bulk imports, and barcode format compliance (e.g. CODE39 vs CODE128).
 */
class SerialNumberController extends Controller
{
    /**
     * Display filtered listing of registered serial numbers.
     *
     * @param SerialFilterRequest $request
     * @return View
     */
    public function index(SerialFilterRequest $request): View
    {
        return view('serials.index', $this->references() + [
            'records' => $this->filtered($request)->paginate(50)->withQueryString(),
        ]);
    }

    /**
     * Generate lazy-loaded serial inventory reporting view.
     *
     * @param SerialFilterRequest $request
     * @return View
     */
    public function report(SerialFilterRequest $request): View
    {
        return view('serials.report', [
            'records' => $this->filtered($request)->lazy(500),
        ]);
    }

    /**
     * Display form to generate or register serial numbers.
     *
     * @return View
     */
    public function create(): View
    {
        $references = $this->references();
        $references['products'] = $references['products']->where('enable_serial', true)->where('manage_stock', true);

        return view('serials.create', $references);
    }

    /**
     * Handle inline quick-reference creation for products or warehouse locations.
     *
     * @param SerialReferenceRequest $request
     * @return RedirectResponse
     */
    public function reference(SerialReferenceRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            $kind = $data['kind'];

            if ($kind === 'product') {
                return redirect()->route('products.catalog.create')->withInput([
                    'name' => $data['name'] ?? null,
                    'sku' => $data['code'] ?? null,
                ]);
            }

            unset($data['kind']);
            $this->databaseTransaction(
                fn () => Location::create($data),
                'Location with this code already exists.',
                'code'
            );

            return back()->with('success', 'Location registered. Its ID is shown in the reference list.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to create reference entity in SerialNumberController', [
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Unable to create reference item.');
        }
    }

    /**
     * Generate a batch of candidate serial numbers and render preview verification.
     *
     * @param GenerateSerialNumbersRequest $request
     * @param SerialNumbers $serialNumbers
     * @return View
     */
    public function preview(GenerateSerialNumbersRequest $request, SerialNumbers $serialNumbers): View
    {
        $data = $request->generatorData();
        $rows = $serialNumbers->generate($data);

        if ($data['barcode_format'] === 'CODE39') {
            foreach ($rows as $row) {
                if (! preg_match('/^[0-9A-Z. $\/+%\-]+$/D', $row['serial_number'])) {
                    throw ValidationException::withMessages([
                        'barcode_format' => 'CODE39 requires uppercase letters, numbers or . $ / + % -. Use CODE128 for other characters.',
                    ]);
                }
            }
        }

        $token = bin2hex(random_bytes(16));
        $request->session()->put('serial_preview', compact('token', 'rows', 'data'));

        return view('serials.preview', compact('token', 'rows', 'data') + ['saved' => false]);
    }

    /**
     * Confirm and persist generated serial numbers from session preview.
     *
     * @param StoreSerialPreviewRequest $request
     * @param SerialNumbers $serialNumbers
     * @return View|RedirectResponse
     */
    public function store(StoreSerialPreviewRequest $request, SerialNumbers $serialNumbers): View|RedirectResponse
    {
        $preview = $request->session()->get('serial_preview');

        try {
            if (! $preview || ! hash_equals($preview['token'], $request->validated('token'))) {
                throw ValidationException::withMessages([
                    'serials' => 'Preview expired. Generate a new preview.',
                ]);
            }
            $serialNumbers->save($preview['rows']);
        } catch (ValidationException $exception) {
            $parameters = $request->boolean('embedded') ? ['embedded' => 1] : [];

            return redirect()->route('products.serials.create', $parameters)
                ->withErrors($exception->errors())
                ->withInput($preview['data'] ?? []);
        } catch (Throwable $exception) {
            Log::error('Failed to commit serial numbers from preview', [
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('products.serials.create')
                ->with('error', 'Failed to save serial numbers: ' . $exception->getMessage())
                ->withInput($preview['data'] ?? []);
        }

        $request->session()->forget('serial_preview');

        return view('serials.preview', $preview + ['saved' => true]);
    }

    /**
     * Delete available, unsold serial numbers in bulk.
     *
     * @param DeleteSerialNumbersRequest $request
     * @return RedirectResponse
     */
    public function destroy(DeleteSerialNumbersRequest $request): RedirectResponse
    {
        try {
            $deleted = $this->databaseTransaction(
                fn () => ProductSerialNumber::whereIn('id', $request->validated('ids'))
                    ->where('status', 'available')
                    ->whereNull('sold_transaction_id')
                    ->delete(),
                'One or more selected serial numbers are linked to a transaction.'
            );

            return back()->with('success', $deleted . ' available serial number(s) deleted. Sold records are protected.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete serial numbers', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Unable to delete serial numbers.');
        }
    }

    /**
     * Download XLSX template file for bulk serial number spreadsheet importing.
     *
     * @param SerialNumberSpreadsheet $spreadsheet
     * @return BinaryFileResponse
     */
    public function template(SerialNumberSpreadsheet $spreadsheet): BinaryFileResponse
    {
        return response()->download(
            $spreadsheet->createTemplate(),
            'serial-numbers-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    /**
     * Import serial numbers from an uploaded Excel spreadsheet.
     *
     * @param ImportSerialNumbersRequest $request
     * @param SerialNumberSpreadsheet $spreadsheet
     * @param SerialNumbers $serialNumbers
     * @return RedirectResponse|JsonResponse
     */
    public function import(
        ImportSerialNumbersRequest $request,
        SerialNumberSpreadsheet $spreadsheet,
        SerialNumbers $serialNumbers
    ): RedirectResponse|JsonResponse {
        try {
            $rows = $spreadsheet->read($request->file('file'));
            $serialNumbers->save($rows);
            $message = count($rows) . ' serial numbers imported successfully.';

            if ($request->expectsJson()) {
                $request->session()->flash('success', $message);

                return response()->json(['status' => 'success', 'message' => $message]);
            }

            return redirect()->route('products.serials.index')->with('success', $message);
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to import serial numbers spreadsheet', [
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Failed to import serial numbers: ' . $e->getMessage()], 422);
            }

            return back()->with('error', 'Spreadsheet import failed: ' . $e->getMessage());
        }
    }

    /**
     * Load product catalog and location references for form dropdowns.
     *
     * @return array
     */
    private function references(): array
    {
        return [
            'products' => Product::with('locations')->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ];
    }

    /**
     * Apply request query filters to serial numbers model query.
     *
     * @param SerialFilterRequest $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function filtered(SerialFilterRequest $request)
    {
        $query = ProductSerialNumber::with(['product', 'location'])->latest('id');

        foreach ($request->validated() as $column => $value) {
            if ($value !== null) {
                $column === 'product_id' ? $query->forProduct($value) : $query->where($column, $value);
            }
        }

        return $query;
    }
}
