<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveInvoiceLayoutRequest;
use App\Http\Requests\SaveInvoiceSchemeRequest;
use App\Models\InvoiceLayout;
use App\Models\InvoiceScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class InvoiceSettingController
 *
 * Manages invoice schemes (prefix, numbering sequence, padding, year formats)
 * and invoice layouts (print designs, headers, customer field visibility, branding logos).
 */
class InvoiceSettingController extends Controller
{
    /**
     * Display a listing of invoice schemes and invoice print layouts.
     *
     * @return View
     */
    public function index(): View
    {
        return view('business.invoice-settings', [
            'schemes' => InvoiceScheme::orderByDesc('is_default')->orderBy('name')->get(),
            'layouts' => InvoiceLayout::with(['posLocations', 'saleLocations'])->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new invoice layout.
     *
     * @return View
     */
    public function createLayout(): View
    {
        return view('business.invoice-layout-form', [
            'layout' => new InvoiceLayout(),
        ]);
    }

    /**
     * Show the form for editing an existing invoice layout.
     *
     * @param InvoiceLayout $invoiceLayout
     * @return View
     */
    public function editLayout(InvoiceLayout $invoiceLayout): View
    {
        return view('business.invoice-layout-form', [
            'layout' => $invoiceLayout->load(['labels', 'options']),
        ]);
    }

    /**
     * Store a newly created invoice layout.
     *
     * @param SaveInvoiceLayoutRequest $request
     * @return RedirectResponse
     */
    public function storeLayout(SaveInvoiceLayoutRequest $request): RedirectResponse
    {
        try {
            $this->persistLayout(new InvoiceLayout(), $request);

            return redirect()->route('business.invoice-settings.index', ['tab' => 'layouts'])
                ->with('status', 'Invoice layout added successfully.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store invoice layout', [
                'error' => $e->getMessage(),
                'payload' => $request->except(['_token', 'logo']),
            ]);

            return back()->withInput()->with('error', 'Failed to create invoice layout. Please try again.');
        }
    }

    /**
     * Update an existing invoice layout.
     *
     * @param SaveInvoiceLayoutRequest $request
     * @param InvoiceLayout $invoiceLayout
     * @return RedirectResponse
     */
    public function updateLayout(SaveInvoiceLayoutRequest $request, InvoiceLayout $invoiceLayout): RedirectResponse
    {
        try {
            $this->persistLayout($invoiceLayout, $request);

            return redirect()->route('business.invoice-settings.index', ['tab' => 'layouts'])
                ->with('status', 'Invoice layout updated successfully.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update invoice layout', [
                'layout_id' => $invoiceLayout->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Failed to update invoice layout. Please try again.');
        }
    }

    /**
     * Store a newly created invoice numbering scheme.
     *
     * @param SaveInvoiceSchemeRequest $request
     * @return RedirectResponse
     */
    public function store(SaveInvoiceSchemeRequest $request): RedirectResponse
    {
        try {
            $this->persist(new InvoiceScheme(), $request->validated());

            return back()->with('status', 'Invoice scheme added successfully.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to store invoice scheme', [
                'error' => $e->getMessage(),
                'payload' => $request->except(['_token']),
            ]);

            return back()->withInput()->with('error', 'Failed to create invoice scheme. Please try again.');
        }
    }

    /**
     * Update an existing invoice numbering scheme.
     *
     * @param SaveInvoiceSchemeRequest $request
     * @param InvoiceScheme $invoiceScheme
     * @return RedirectResponse
     */
    public function update(SaveInvoiceSchemeRequest $request, InvoiceScheme $invoiceScheme): RedirectResponse
    {
        try {
            $this->persist($invoiceScheme, $request->validated());

            return back()->with('status', 'Invoice scheme updated successfully.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update invoice scheme', [
                'scheme_id' => $invoiceScheme->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Failed to update invoice scheme. Please try again.');
        }
    }

    /**
     * Delete an invoice numbering scheme if it is not default and unassigned.
     *
     * @param InvoiceScheme $invoiceScheme
     * @return RedirectResponse
     */
    public function destroy(InvoiceScheme $invoiceScheme): RedirectResponse
    {
        try {
            if ($invoiceScheme->is_default) {
                return back()->with('error', 'The default invoice scheme cannot be deleted.');
            }
            if ($invoiceScheme->locations()->exists()) {
                return back()->with('error', 'This invoice scheme is assigned to a business location.');
            }

            $invoiceScheme->delete();

            return back()->with('status', 'Invoice scheme deleted successfully.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete invoice scheme', [
                'scheme_id' => $invoiceScheme->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to delete invoice scheme.');
        }
    }

    /**
     * Atomically persist invoice scheme parameters.
     *
     * @param InvoiceScheme $scheme
     * @param array $data
     * @return void
     */
    private function persist(InvoiceScheme $scheme, array $data): void
    {
        DB::transaction(function () use ($scheme, $data) {
            // Format 2 has the year as its default prefix, for example 2026-0000.
            $data['prefix'] = trim((string) ($data['prefix'] ?? '')) ?: ($data['format'] === 'year_number' ? now()->year . '-' : '#');
            $makeDefault = (bool) ($data['is_default'] ?? false) || ! InvoiceScheme::where('is_default', true)->exists();

            if ($makeDefault) {
                InvoiceScheme::where('is_default', true)
                    ->when($scheme->exists, fn ($query) => $query->whereKeyNot($scheme->id))
                    ->update(['is_default' => false]);
            }

            $data['is_default'] = $makeDefault;
            if (! $scheme->exists) {
                $data['current_number'] = $data['start_number'];
            } elseif ($scheme->invoice_count === 0) {
                $data['current_number'] = $data['start_number'];
            }

            $scheme->fill($data)->save();
        });
    }

    /**
     * Atomically persist invoice layout and associated custom label overrides and print toggles.
     *
     * @param InvoiceLayout $layout
     * @param SaveInvoiceLayoutRequest $request
     * @return void
     */
    private function persistLayout(InvoiceLayout $layout, SaveInvoiceLayoutRequest $request): void
    {
        $data = $request->validated();
        $labels = $data['labels'] ?? [];
        $options = $data['options'] ?? [];
        unset($data['labels'], $data['options'], $data['logo']);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $data['logo_path'] = 'data:' . ($file->getMimeType() ?: 'image/jpeg') . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
        }

        $data['is_default'] = (bool) ($data['is_default'] ?? false) || ! InvoiceLayout::where('is_default', true)->exists();
        $data['logo_path'] = $data['logo_path'] ?? $layout->logo_path;

        DB::transaction(function () use ($layout, $data, $labels, $options) {
            if ($data['is_default']) {
                InvoiceLayout::where('is_default', true)
                    ->when($layout->exists, fn ($q) => $q->whereKeyNot($layout->id))
                    ->update(['is_default' => false]);
            }

            $layout->fill($data)->save();

            $layout->labels()->delete();
            $layout->labels()->createMany(
                collect($labels)
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->map(fn ($v, $k) => ['key' => $k, 'value' => $v])
                    ->values()
                    ->all()
            );

            $layout->options()->delete();
            $layout->options()->createMany(
                collect($options)
                    ->map(fn ($v, $k) => ['key' => $k, 'enabled' => (bool) $v])
                    ->values()
                    ->all()
            );
        });
    }
}
