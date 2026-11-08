<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Service;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SalesOrderController extends Controller
{
    /**
     * Display a combined sales dashboard showing both invoices and unconverted sales orders.
     */
    public function index()
    {
        try {
            // Retrieve latest invoices with their customer, salesperson, and linked sales order
            $invoices = SalesInvoice::with(['customer', 'salesperson', 'salesOrder.salesperson'])
                ->latest()
                ->limit(100)
                ->get();

            // Retrieve unconverted sales orders (orders that do not have an invoice generated yet)
            $orders = SalesOrder::with(['customer', 'salesperson'])
                ->whereNotIn('id', SalesInvoice::select('sales_order_id')->whereNotNull('sales_order_id'))
                ->latest()
                ->limit(100)
                ->get();

            // Transform into unified recent sales stream
            $recentSales = $invoices->map(fn ($i) => [
                'customer' => $i->customer?->name,
                'salesperson' => $i->salesperson?->name ?? $i->salesOrder?->salesperson?->name,
                'order' => $i->salesOrder,
                'invoice' => $i,
                'status' => $i->status === 'draft' ? 'Draft' : ($i->status === 'credited' ? 'Credited' : 'Success'),
                'created_at' => $i->created_at,
            ])->concat($orders->map(fn ($o) => [
                'customer' => $o->customer?->name,
                'salesperson' => $o->salesperson?->name,
                'order' => $o,
                'invoice' => null,
                'status' => 'Follow Up',
                'created_at' => $o->created_at,
            ]))->sortByDesc('created_at')->take(100)->values();

            return view('sales-orders.dashboard', compact('recentSales'));
        } catch (Throwable $e) {
            Log::error('Failed to load sales orders dashboard: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Unable to load sales dashboard. Please refresh or try again.');
        }
    }

    /**
     * Show the sales order creation form with required lookup data.
     */
    public function create()
    {
        try {
            $data = $this->references();

            // Format next sequence number e.g. 0129
            $lastId = SalesOrder::max('id') ?? 0;
            $data['nextOrderNumber'] = str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);

            // Customers lookup
            $customers = Contact::whereIn('type', ['customer', 'both'])
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            // Safe query for recent invoices per customer
            $customerIds = $customers->pluck('id')->filter()->all();
            $recentInvoicesByCustomer = !empty($customerIds)
                ? SalesInvoice::whereIn('customer_id', $customerIds)->latest()->get()->groupBy('customer_id')
                : collect();

            $data['customersList'] = $customers;
            $data['customersJson'] = $customers->map(function ($c) use ($recentInvoicesByCustomer) {
                $invs = $recentInvoicesByCustomer->get($c->id, collect())->take(5);

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'business_name' => $c->business_name ?? '',
                    'address' => trim(($c->address_line_1 ?? '') . ' ' . ($c->address_line_2 ?? '') . ' ' . ($c->city ?? '')) ?: '—',
                    'mobile' => $c->mobile ?? $c->alternate_number ?? '—',
                    'email' => $c->email ?? '—',
                    'credit_limit' => number_format((float) ($c->credit_limit ?? 0), 2),
                    'balance' => number_format((float) ($c->opening_balance ?? 0), 2),
                    'total_due' => number_format((float) ($c->opening_balance ?? 0), 2),
                    'invoices' => $invs->map(fn ($inv) => [
                        'invoice_number' => $inv->invoice_number,
                        'date' => $inv->invoice_date?->format('Y-m-d') ?? $inv->created_at?->format('Y-m-d'),
                        'amount' => number_format((float) $inv->total_amount, 2),
                        'status' => ucfirst($inv->status),
                    ])->values(),
                ];
            });

            // Products JSON with prices and available quantity
            $data['productsJson'] = Product::where('is_active', true)
                ->where('not_for_selling', false)
                ->orderBy('name')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code ?? '',
                    'price' => (float) ($p->selling_price ?? 0),
                    'available_qty' => (float) ($p->alert_quantity ?? 50),
                ]);

            // Services JSON with rate and details
            $data['servicesJson'] = Service::where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
                ->orderBy('name')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'code' => $s->code ?? '',
                    'price' => (float) ($s->unit_rate ?? 0),
                    'cost' => (float) ($s->unit_cost ?? 0),
                    'description' => $s->description ?? '',
                ]);

            return view('sales-orders.form', $data);
        } catch (Throwable $e) {
            Log::error('Failed to load sales order form references: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Unable to initialize sales order form: ' . $e->getMessage());
        }
    }

    /**
     * Atomically store a newly created sales order with line items.
     */
    public function store(Request $request)
    {
        // 1. Validate incoming sales order payload
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['required', 'exists:contacts,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'salesperson_id' => ['nullable', 'exists:users,id'],
            'order_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'payment_terms' => ['nullable', 'string', 'max:5000'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'net_total' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_type' => ['nullable', 'string', 'in:item,service,text'],
            'lines.*.product_id' => ['nullable', 'exists:products,id'],
            'lines.*.service_id' => ['nullable', 'exists:services,id'],
            'lines.*.product_lot_id' => ['nullable', 'exists:product_lots,id'],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.available_qty' => ['nullable', 'numeric'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_total' => ['nullable', 'numeric'],
        ]);

        $lines = $data['lines'];
        unset($data['lines']);

        try {
            // 2. Perform atomic database transaction for order header and lines
            $order = DB::transaction(function () use ($data, $lines, $request) {
                // Ensure unique order number
                $orderNumber = $data['order_number'] ?? null;
                if (!$orderNumber) {
                    $lastId = SalesOrder::max('id') ?? 0;
                    $orderNumber = str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);
                }

                $grossTotal = 0;
                foreach ($lines as $l) {
                    $qty = (float) ($l['quantity'] ?? 1);
                    $price = (float) ($l['unit_price'] ?? 0);
                    $disc = (float) ($l['discount_amount'] ?? 0);
                    $grossTotal += ($qty * $price) - $disc;
                }

                $discPercent = (float) ($data['discount_percentage'] ?? 0);
                $discAmount = (float) ($data['discount_amount'] ?? 0);
                if ($discPercent > 0 && $discAmount == 0) {
                    $discAmount = ($grossTotal * $discPercent) / 100;
                }
                $netTotal = max(0, $grossTotal - $discAmount);

                $orderData = array_merge($data, [
                    'order_number' => $orderNumber,
                    'title' => $data['title'] ?? 'Order',
                    'total_amount' => $grossTotal,
                    'discount_percentage' => $discPercent,
                    'discount_amount' => $discAmount,
                    'net_total' => $netTotal,
                    'status' => 'open',
                    'created_by' => $request->user()->id,
                ]);

                $order = SalesOrder::create($orderData);

                // Insert associated line items
                foreach ($lines as $i => $line) {
                    $qty = (float) ($line['quantity'] ?? 1);
                    $price = (float) ($line['unit_price'] ?? 0);
                    $discAmt = (float) ($line['discount_amount'] ?? 0);
                    $lineTotal = max(0, ($qty * $price) - $discAmt);

                    $order->lines()->create([
                        'product_id' => $line['product_id'] ?? null,
                        'service_id' => $line['service_id'] ?? null,
                        'product_lot_id' => $line['product_lot_id'] ?? null,
                        'item_type' => $line['item_type'] ?? 'item',
                        'description' => $line['description'] ?? 'Item',
                        'available_qty' => $line['available_qty'] ?? null,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'discount_percentage' => (float) ($line['discount_percentage'] ?? 0),
                        'discount_amount' => $discAmt,
                        'line_total' => $lineTotal,
                        'sort_order' => $i,
                    ]);
                }

                return $order;
            }, 3);

            return redirect()->route('sales.index')->with('success', 'Sales Order #' . $order->order_number . ' created successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database integrity conflict during sales order creation: '.$e->getMessage(), [
                'user_id' => $request->user()->id,
                'data' => $data,
            ]);

            return back()->withInput()->with('error', 'A database conflict occurred while saving the sales order: ' . $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Unexpected error while creating sales order: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->with('error', 'An unexpected error occurred while creating the sales order: ' . $e->getMessage());
        }
    }

    /**
     * Provide lookup datasets for sales order forms.
     */
    private function references(): array
    {
        return [
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'salespeople' => User::where('status', 'active')->orderBy('name')->get(),
            'taxes' => TaxRate::availableForProducts()->orderBy('name')->get(),
        ];
    }

    /**
     * Display dispatches listing matching AntERP /Transactions/dispatches design.
     */
    public function dispatches(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $query = \App\Models\SalesDispatch::with(['items', 'vehicle', 'customer', 'salesperson'])
            ->latest('dispatch_date')
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $search = '%' . trim($request->input('q')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('dispatch_number', 'like', $search)
                  ->orWhere('order_number', 'like', $search)
                  ->orWhere('invoice_number', 'like', $search)
                  ->orWhere('customer_name', 'like', $search)
                  ->orWhere('status', 'like', $search)
                  ->orWhere('dispatch_date', 'like', $search);
            });
        }

        $dispatches = $query->paginate($perPage)->withQueryString();
        $vehicles = \App\Models\DeliveryVehicle::where('is_active', true)->orderBy('number')->get();

        if ($request->ajax() || $request->has('ajax')) {
            return response()->json([
                'rows_html' => view('sales-orders.partials.dispatches-rows', compact('dispatches'))->render(),
                'pagination_html' => view('sales-orders.partials.dispatches-pagination', compact('dispatches'))->render(),
                'total' => $dispatches->total(),
                'firstItem' => $dispatches->firstItem() ?? 0,
                'lastItem' => $dispatches->lastItem() ?? 0,
            ]);
        }

        return view('sales-orders.dispatches', compact('dispatches', 'vehicles', 'perPage'));
    }

    /**
     * Return JSON details for a single dispatch note modal.
     */
    public function showDispatch(\App\Models\SalesDispatch $dispatch)
    {
        $dispatch->load(['items', 'vehicle', 'customer', 'salesperson']);

        return response()->json([
            'id' => $dispatch->id,
            'dispatch_number' => $dispatch->dispatch_number,
            'order_number' => $dispatch->order_number ?: '',
            'invoice_number' => $dispatch->invoice_number ?: '',
            'invoice_date' => $dispatch->invoice_date ? $dispatch->invoice_date->format('Y-m-d') : '',
            'salesperson_name' => $dispatch->salesperson_name ?: ($dispatch->salesperson?->name ?: ''),
            'customer_name' => $dispatch->customer_name ?: ($dispatch->customer?->name ?: ''),
            'delivery_vehicle_id' => $dispatch->delivery_vehicle_id,
            'vehicle_number' => $dispatch->vehicle_number ?: ($dispatch->vehicle?->number ?: ''),
            'status' => $dispatch->status,
            'items' => $dispatch->items->map(fn ($item) => [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
                'inv_qty' => (float) $item->inv_qty,
                'qty' => (float) $item->qty,
            ]),
        ]);
    }

    /**
     * Update dispatch note vehicle and quantities from modal.
     */
    public function updateDispatch(Request $request, \App\Models\SalesDispatch $dispatch)
    {
        $validated = $request->validate([
            'delivery_vehicle_id' => ['nullable', 'exists:delivery_vehicles,id'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'exists:sales_dispatch_items,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
        ]);

        if (!empty($validated['delivery_vehicle_id'])) {
            $vehicle = \App\Models\DeliveryVehicle::find($validated['delivery_vehicle_id']);
            $dispatch->delivery_vehicle_id = $vehicle?->id;
            $dispatch->vehicle_number = $vehicle?->number;
        }

        if (!empty($validated['items'])) {
            foreach ($validated['items'] as $itemData) {
                \App\Models\SalesDispatchItem::where('id', $itemData['id'])
                    ->where('sales_dispatch_id', $dispatch->id)
                    ->update(['qty' => $itemData['qty']]);
            }
        }

        $dispatch->save();

        return response()->json([
            'success' => true,
            'message' => 'Dispatch note ' . $dispatch->dispatch_number . ' updated successfully.',
            'vehicle_number' => $dispatch->vehicle_number,
        ]);
    }
}
