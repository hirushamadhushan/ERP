<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
use Illuminate\Validation\ValidationException;

class SerialNumberController extends Controller
{
    public function index(SerialFilterRequest $request)
    {
        return view('serials.index', $this->references() + [
            'records' => $this->filtered($request)->paginate(50)->withQueryString(),
        ]);
    }

    public function report(SerialFilterRequest $request)
    {
        return view('serials.report', ['records' => $this->filtered($request)->lazy(500)]);
    }

    public function create()
    {
        $references = $this->references();
        $references['products'] = $references['products']->where('enable_serial', true)->where('manage_stock', true);

        return view('serials.create', $references);
    }

    public function reference(SerialReferenceRequest $request)
    {
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
    }

    public function preview(GenerateSerialNumbersRequest $request, SerialNumbers $serialNumbers)
    {
        $data = $request->generatorData();
        $rows = $serialNumbers->generate($data);
        if ($data['barcode_format'] === 'CODE39') {
            foreach ($rows as $row) {
                if (! preg_match('/^[0-9A-Z. $\/+%\-]+$/D', $row['serial_number'])) {
                    throw ValidationException::withMessages(['barcode_format' => 'CODE39 requires uppercase letters, numbers or . $ / + % -. Use CODE128 for other characters.']);
                }
            }
        }

        $token = bin2hex(random_bytes(16));
        $request->session()->put('serial_preview', compact('token', 'rows', 'data'));

        return view('serials.preview', compact('token', 'rows', 'data') + ['saved' => false]);
    }

    public function store(StoreSerialPreviewRequest $request, SerialNumbers $serialNumbers)
    {
        $preview = $request->session()->get('serial_preview');
        try {
            if (! $preview || ! hash_equals($preview['token'], $request->validated('token'))) {
                throw ValidationException::withMessages(['serials' => 'Preview expired. Generate a new preview.']);
            }
            $serialNumbers->save($preview['rows']);
        } catch (ValidationException $exception) {
            $parameters = $request->boolean('embedded') ? ['embedded' => 1] : [];

            return redirect()->route('products.serials.create', $parameters)
                ->withErrors($exception->errors())
                ->withInput($preview['data'] ?? []);
        }
        $request->session()->forget('serial_preview');

        return view('serials.preview', $preview + ['saved' => true]);
    }

    public function destroy(DeleteSerialNumbersRequest $request)
    {
        $deleted = $this->databaseTransaction(
            fn () => ProductSerialNumber::whereIn('id', $request->validated('ids'))
                ->where('status', 'available')
                ->whereNull('sold_transaction_id')
                ->delete(),
            'One or more selected serial numbers are linked to a transaction.'
        );

        return back()->with('success', $deleted.' available serial number(s) deleted. Sold records are protected.');
    }

    public function template(SerialNumberSpreadsheet $spreadsheet)
    {
        return response()->download(
            $spreadsheet->createTemplate(),
            'serial-numbers-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportSerialNumbersRequest $request, SerialNumberSpreadsheet $spreadsheet, SerialNumbers $serialNumbers)
    {
        $rows = $spreadsheet->read($request->file('file'));
        $serialNumbers->save($rows);
        $message = count($rows).' serial numbers imported successfully.';

        if ($request->expectsJson()) {
            $request->session()->flash('success', $message);

            return response()->json(['status' => 'success', 'message' => $message]);
        }

        return redirect()->route('products.serials.index')->with('success', $message);
    }

    private function references(): array
    {
        return [
            'products' => Product::with('locations')->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
        ];
    }

    private function filtered(SerialFilterRequest $request)
    {
        $query = ProductSerialNumber::with(['product', 'location'])->latest('id');
        foreach ($request->validated() as $column => $value) {
            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        return $query;
    }
}
