<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
            return view('sales-orders.form', $this->references());
        } catch (Throwable $e) {
            Log::error('Failed to load sales order form references: '.$e->getMessage());

            return back()->with('error', 'Unable to initialize sales order form.');
        }
    }

    /**
     * Atomically store a newly created sales order with line items.
     */
    public function store(Request $request)
    {
        // 1. Validate incoming sales order payload and items
        $data = $request->validate([
            'customer_id' => ['required', 'exists:contacts,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'salesperson_id' => ['nullable', 'exists:users,id'],
            'commission_agent_id' => ['nullable', 'exists:contacts,id'],
            'order_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'lines.*.product_lot_id' => ['nullable', 'exists:product_lots,id'],
            'lines.*.tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $lines = $data['lines'];
        unset($data['lines']);

        try {
            // 2. Perform atomic database transaction for order header and lines
            $order = DB::transaction(function () use ($data, $lines, $request) {
                // Generate sequential order number (SO/YYYY/NNNNN)
                $orderNumber = 'SO/'.now()->year.'/'.str_pad((string) (SalesOrder::max('id') + 1), 5, '0', STR_PAD_LEFT);
                $totalAmount = collect($lines)->sum(fn ($l) => (float) $l['quantity'] * (float) $l['unit_price']);

                $order = SalesOrder::create($data + [
                    'order_number' => $orderNumber,
                    'total_amount' => $totalAmount,
                    'created_by' => $request->user()->id,
                ]);

                // Insert associated line items maintaining sort order
                foreach ($lines as $i => $line) {
                    $order->lines()->create($line + ['sort_order' => $i]);
                }

                return $order;
            }, 3);

            return redirect()->route('sales.index')->with('success', 'Sales order '.$order->order_number.' created.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database integrity conflict during sales order creation: '.$e->getMessage(), [
                'user_id' => $request->user()->id,
                'data' => $data,
            ]);

            return back()->withInput()->with('error', 'A database conflict occurred while saving the sales order. Please verify the entered data.');
        } catch (Throwable $e) {
            Log::error('Unexpected error while creating sales order: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->with('error', 'An unexpected error occurred while creating the sales order. Please try again.');
        }
    }

    /**
     * Provide lookup datasets for sales order forms.
     */
    private function references(): array
    {
        return [
            'customers' => Contact::whereIn('type', ['customer', 'both'])->where('status', 'active')->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'salespeople' => User::where('status', 'active')->orderBy('name')->get(),
            'agents' => Contact::where('type', 'commission')->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->where('not_for_selling', false)->orderBy('name')->get(),
            'taxes' => TaxRate::availableForProducts()->orderBy('name')->get(),
        ];
    }
}
