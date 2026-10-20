<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSalesInvoiceRequest;
use App\Mail\SalesInvoiceMail;
use App\Models\BusinessSetting;
use App\Models\Contact;
use App\Models\Location;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceCreditNote;
use App\Models\SalesOrder;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CreditNotePdf;
use App\Services\InvoicePdf;
use App\Services\SalesInvoiceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class SalesInvoiceController extends Controller
{
    /**
     * Display a listing of all invoices (including draft, posted, credited).
     */
    public function index()
    {
        try {
            $invoices = SalesInvoice::with('customer')->latest()->get();

            return view('invoices.index', [
                'invoices' => $invoices,
                'salesView' => false,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve invoices index: '.$e->getMessage());

            return back()->with('error', 'Unable to retrieve invoices list.');
        }
    }

    /**
     * Display posted/active sales view (excluding unconfirmed drafts).
     */
    public function sales()
    {
        try {
            $invoices = SalesInvoice::with('customer')->where('status', '!=', 'draft')->latest()->get();

            return view('invoices.index', [
                'invoices' => $invoices,
                'salesView' => true,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve sales records: '.$e->getMessage());

            return back()->with('error', 'Unable to retrieve sales records.');
        }
    }

    /**
     * Show the invoice creation form, optionally pre-filled from an unconverted sales order.
     */
    public function create(Request $request)
    {
        try {
            $invoice = new SalesInvoice([
                'invoice_date' => today(),
                'journal' => 'Sales',
                'tax_display' => 'exclusive',
                'status' => 'draft',
            ]);
            $preset = [];

            // Pre-fill fields if initiated from a specific sales order
            if ($request->filled('order')) {
                $order = SalesOrder::with('lines')->where('status', 'open')->findOrFail($request->integer('order'));
                $invoice->fill([
                    'sales_order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'location_id' => $order->location_id,
                    'salesperson_id' => $order->salesperson_id,
                    'commission_agent_id' => $order->commission_agent_id,
                    'delivery_date' => $order->delivery_date,
                    'terms' => $order->terms,
                ]);
                $preset = $order->lines->map(fn ($line) => $line->only([
                    'product_id', 'product_lot_id', 'tax_rate_id', 'description', 'quantity', 'unit_price',
                ]) + ['line_type' => 'product'])->all();
            }

            return $this->form($invoice, $preset);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to initialize invoice creation form: '.$e->getMessage());

            return back()->with('error', 'Unable to initialize invoice creation form.');
        }
    }

    /**
     * Show the invoice editor with eager-loaded relations for complete audit trail.
     */
    public function edit(SalesInvoice $invoice)
    {
        try {
            return $this->form(
                $invoice->load([
                    'customer',
                    'lines',
                    'journalItems',
                    'installments',
                    'payments.account',
                    'payments.creator',
                    'creditAllocations.creator',
                    'creditNotes.creator',
                    'creditNotes.lines',
                    'activities.creator',
                ])
            );
        } catch (Throwable $e) {
            Log::error('Failed to load invoice for editing ID '.$invoice->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to load invoice details.');
        }
    }

    /**
     * Store a newly created sales invoice and optionally confirm/post it immediately.
     */
    public function store(SaveSalesInvoiceRequest $request, SalesInvoiceService $service)
    {
        // Enforce authorization for immediate posting/confirmation
        if ($request->input('intent') === 'confirm' || $request->boolean('auto_post')) {
            $role = $request->user()->assignedRole;
            abort_if($role && strcasecmp($role->name, 'admin') !== 0 && ! in_array('invoice.post', $role->permissions, true), 403);
        }

        try {
            $invoice = $service->save(null, $request->validated(), auth()->id());

            if ($request->input('intent') === 'confirm') {
                $service->post($invoice, auth()->id());
            }

            if ($invoice->sales_order_id) {
                SalesOrder::whereKey($invoice->sales_order_id)->update(['status' => 'converted']);
            }

            $message = $invoice->fresh()->status === 'posted' ? 'Invoice confirmed and posted.' : 'Invoice saved.';

            return redirect()->route('invoices.edit', $invoice)->with('success', $message);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database conflict while saving invoice: '.$e->getMessage(), ['user_id' => auth()->id()]);

            return back()->withInput()->with('error', 'A database conflict occurred while saving the invoice.');
        } catch (Throwable $e) {
            Log::error('Unexpected error while creating invoice: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'An unexpected error occurred while saving the invoice. Please try again.');
        }
    }

    /**
     * Update an existing sales invoice and recalculate totals.
     */
    public function update(SaveSalesInvoiceRequest $request, SalesInvoice $invoice, SalesInvoiceService $service)
    {
        $role = $request->user()->assignedRole;
        abort_if($request->boolean('auto_post') && $role && strcasecmp($role->name, 'admin') !== 0 && ! in_array('invoice.post', $role->permissions, true), 403);

        try {
            $service->save($invoice, $request->validated(), auth()->id());

            return back()->with('success', 'Invoice saved.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database conflict updating invoice ID '.$invoice->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'A database conflict occurred while updating the invoice.');
        } catch (Throwable $e) {
            Log::error('Unexpected error updating invoice ID '.$invoice->id.': '.$e->getMessage());

            return back()->withInput()->with('error', 'An unexpected error occurred while updating the invoice.');
        }
    }

    /**
     * Post a draft invoice to ledger, finalizing tax, receivables, and generating double-entry journal items.
     */
    public function post(SalesInvoice $invoice, SalesInvoiceService $service)
    {
        try {
            $service->post($invoice, auth()->id());

            return back()->with('success', 'Invoice confirmed and posted.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to post invoice ID '.$invoice->id.': '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->with('error', 'Unable to confirm invoice: '.$e->getMessage());
        }
    }

    /**
     * Reset a posted invoice back to draft status for adjustments if conditions allow.
     */
    public function resetDraft(SalesInvoice $invoice, SalesInvoiceService $service)
    {
        try {
            $service->resetToDraft($invoice, auth()->id());

            return back()->with('success', 'Invoice reset to draft.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to reset invoice ID '.$invoice->id.' to draft: '.$e->getMessage());

            return back()->with('error', 'Unable to reset invoice to draft: '.$e->getMessage());
        }
    }

    /**
     * Register a payment transaction against the invoice and account ledger.
     */
    public function payment(Request $request, SalesInvoice $invoice, SalesInvoiceService $service)
    {
        $data = $request->validate([
            'payment_account_id' => ['required', Rule::exists('payment_accounts', 'id')->where('is_active', true)],
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:60',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $service->registerPayment($invoice, $data, auth()->id());

            return back()->with('success', 'Payment registered successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to register payment on invoice ID '.$invoice->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to register payment: '.$e->getMessage());
        }
    }

    /**
     * Apply available customer credit / advance balance to settle this invoice.
     */
    public function applyCredit(SalesInvoice $invoice, SalesInvoiceService $service)
    {
        try {
            $service->applyCustomerCredit($invoice, auth()->id());

            return back()->with('success', 'Customer advance credit applied.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to apply credit on invoice ID '.$invoice->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to apply customer credit: '.$e->getMessage());
        }
    }

    /**
     * Issue a credit note against this invoice for returns or price adjustments.
     */
    public function creditNote(Request $request, SalesInvoice $invoice, SalesInvoiceService $service)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $service->createCreditNote($invoice, $data, auth()->id());

            return back()->with('success', 'Credit note issued.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to create credit note for invoice ID '.$invoice->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to issue credit note: '.$e->getMessage());
        }
    }

    /**
     * Deliver invoice via email, postal dispatch, or download.
     */
    public function send(Request $request, SalesInvoice $invoice, InvoicePdf $pdf)
    {
        if ($invoice->status === 'draft') {
            throw ValidationException::withMessages(['invoice' => 'Confirm the invoice before sending it.']);
        }

        $data = $request->validate([
            'by_email' => 'nullable|boolean',
            'by_post' => 'nullable|boolean',
            'download' => 'nullable|boolean',
            'to' => 'nullable|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
        ]);

        if (empty($data['by_email']) && empty($data['by_post']) && empty($data['download'])) {
            throw ValidationException::withMessages(['delivery' => 'Choose at least one delivery method.']);
        }

        try {
            if (! empty($data['by_email'])) {
                if (empty($data['to'])) {
                    throw ValidationException::withMessages(['to' => 'Enter the customer email address.']);
                }
                if (in_array(config('mail.default'), ['log', 'array'], true) && ! app()->environment('testing')) {
                    throw ValidationException::withMessages(['email' => 'Email delivery is not configured. Set up an SMTP or other delivery mailer before sending.']);
                }

                $document = $pdf->bytes($invoice);
                Mail::to($data['to'])->send(new SalesInvoiceMail(
                    $invoice->invoice_number,
                    $data['subject'] ?: 'Invoice '.$invoice->invoice_number,
                    $data['message'] ?: 'Please find your invoice attached.',
                    $document
                ));

                $invoice->activities()->create([
                    'type' => 'email',
                    'message' => 'Invoice emailed to '.$data['to'].'.',
                    'created_by' => auth()->id(),
                ]);
            }

            if (! empty($data['by_post'])) {
                $invoice->activities()->create([
                    'type' => 'system',
                    'message' => 'Postal delivery requested. Print the invoice and arrange mailing.',
                    'created_by' => auth()->id(),
                ]);
            }

            if (! empty($data['download'])) {
                $invoice->activities()->create([
                    'type' => 'system',
                    'message' => 'Printable invoice prepared for download.',
                    'created_by' => auth()->id(),
                ]);
            }

            return back()->with('success', ! empty($data['by_email']) ? 'Invoice email sent.' : 'Invoice delivery options saved.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed during invoice send operation ID '.$invoice->id.': '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Unable to send invoice: '.$e->getMessage());
        }
    }

    /**
     * Record a manual note or activity against the invoice timeline.
     */
    public function activity(Request $request, SalesInvoice $invoice)
    {
        $data = $request->validate([
            'type' => 'required|in:note,activity',
            'message' => 'required|string|max:2000',
        ]);

        try {
            $invoice->activities()->create([
                'type' => $data['type'],
                'message' => $data['message'],
                'created_by' => auth()->id(),
            ]);

            return back()->with('success', $data['type'] === 'note' ? 'Note added.' : 'Activity recorded.');
        } catch (Throwable $e) {
            Log::error('Failed to record activity for invoice ID '.$invoice->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to record activity.');
        }
    }

    /**
     * Render the printable version of the invoice.
     */
    public function print(SalesInvoice $invoice)
    {
        try {
            return view('invoices.print', [
                'invoice' => $invoice->load(['customer', 'lines.product', 'installments', 'payments']),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to render print view for invoice ID '.$invoice->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to prepare invoice print view.');
        }
    }

    /**
     * Stream or download generated PDF for the sales invoice.
     */
    public function pdf(SalesInvoice $invoice, InvoicePdf $pdf)
    {
        try {
            return response($pdf->bytes($invoice), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$pdf->filename($invoice).'"',
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to generate PDF for invoice ID '.$invoice->id.': '.$e->getMessage());

            return back()->with('error', 'Unable to generate invoice PDF.');
        }
    }

    /**
     * Show credit note details.
     */
    public function creditNoteShow(SalesInvoice $invoice, SalesInvoiceCreditNote $creditNote)
    {
        $this->ensureCreditNoteBelongsToInvoice($invoice, $creditNote);

        try {
            return view('invoices.credit-note', [
                'invoice' => $invoice->loadMissing('customer'),
                'creditNote' => $creditNote->load(['lines', 'journalItems']),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to load credit note view: '.$e->getMessage());

            return back()->with('error', 'Unable to load credit note.');
        }
    }

    /**
     * Download generated PDF for a credit note.
     */
    public function creditNotePdf(SalesInvoice $invoice, SalesInvoiceCreditNote $creditNote, CreditNotePdf $pdf)
    {
        $this->ensureCreditNoteBelongsToInvoice($invoice, $creditNote);

        try {
            return response($pdf->bytes($creditNote), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$pdf->filename($creditNote).'"',
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to generate credit note PDF: '.$e->getMessage());

            return back()->with('error', 'Unable to generate credit note PDF.');
        }
    }

    /**
     * Verify that credit note belongs to the specified parent invoice.
     */
    private function ensureCreditNoteBelongsToInvoice(SalesInvoice $invoice, SalesInvoiceCreditNote $creditNote): void
    {
        abort_unless((int) $creditNote->sales_invoice_id === (int) $invoice->id, 404);
    }

    /**
     * Build invoice creation/editing form with all lookups and lot availability.
     */
    private function form(SalesInvoice $invoice, array $presetLines = [])
    {
        $numberService = app(SalesInvoiceService::class);
        $invoiceNumberPreviews = ['default' => $invoice->invoice_number ?: $numberService->previewNumber()];

        if (! $invoice->exists) {
            foreach (Location::where('is_active', true)->pluck('id') as $locationId) {
                $invoiceNumberPreviews[$locationId] = $numberService->previewNumber($locationId);
            }
        }

        $availableLots = DB::table('product_lots')
            ->join('product_stock_items', 'product_stock_items.id', '=', 'product_lots.product_stock_item_id')
            ->join('inventory_movements', 'inventory_movements.product_lot_id', '=', 'product_lots.id')
            ->join('locations', 'locations.id', '=', 'inventory_movements.location_id')
            ->selectRaw('product_lots.id, product_lots.lot_number, product_lots.expires_at, product_stock_items.product_id, locations.id as location_id, locations.name as location_name, SUM(inventory_movements.quantity_delta) as available')
            ->groupBy('product_lots.id', 'product_lots.lot_number', 'product_lots.expires_at', 'product_stock_items.product_id', 'locations.id', 'locations.name')
            ->havingRaw('SUM(inventory_movements.quantity_delta) > 0')
            ->orderBy('product_lots.expires_at')
            ->orderBy('product_lots.lot_number')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($lots) => $lots->map(fn ($lot) => [
                'id' => (int) $lot->id,
                'number' => $lot->lot_number,
                'location_id' => (int) $lot->location_id,
                'location' => $lot->location_name,
                'available' => (float) $lot->available,
                'expires_at' => $lot->expires_at,
            ])->values())
            ->all();

        return view('invoices.form', [
            'invoice' => $invoice,
            'customers' => Contact::whereIn('type', ['customer', 'both'])->where('status', 'active')->orderBy('name')->get(),
            'agents' => Contact::where('type', 'commission')->where('status', 'active')->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::with(['applicableTax', 'locations', 'variants.locationStocks'])->where('is_active', true)->where('not_for_selling', false)->orderBy('name')->get(),
            'taxes' => TaxRate::availableForProducts()->orderBy('name')->get(),
            'salespeople' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'availableLots' => $availableLots,
            'availableSerialStock' => DB::table('product_serial_numbers')
                ->leftJoin('product_variants', 'product_variants.id', '=', 'product_serial_numbers.product_variant_id')
                ->where('product_serial_numbers.status', 'available')
                ->selectRaw('COALESCE(product_serial_numbers.product_id, product_variants.product_id) as product_id, product_serial_numbers.location_id, COUNT(*) as available')
                ->groupByRaw('COALESCE(product_serial_numbers.product_id, product_variants.product_id), product_serial_numbers.location_id')
                ->get()
                ->groupBy('product_id'),
            'commissionAgentEnabled' => (bool) BusinessSetting::current()->enable_commission_agent,
            'presetLines' => $presetLines,
            'invoiceNumberPreviews' => $invoiceNumberPreviews,
            'openOrders' => SalesOrder::with(['customer', 'salesperson'])->where('status', 'open')->latest()->get(),
            'bankAccounts' => PaymentAccount::orderBy('name')->get(['id', 'name']),
            'paymentAccounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'account_number']),
        ]);
    }
}
