<?php

namespace App\Http\Controllers;

use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceConsumable;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ServiceController extends Controller
{
    /**
     * Display a listing of the services.
     */
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        $search = trim((string) $request->input('search', ''));

        $query = Service::with(['unit', 'costLedger', 'revenueLedger'])
            ->withCount('consumables');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $services = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        $units = Unit::orderBy('name')->get(['id', 'name', 'short_name']);
        $paymentAccounts = PaymentAccount::orderBy('name')->get(['id', 'name', 'account_number']);
        $stockProducts = Product::where('is_active', true)
            ->where('not_for_selling', false)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('products.services.index', [
            'services' => $services,
            'units' => $units,
            'paymentAccounts' => $paymentAccounts,
            'stockProducts' => $stockProducts,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'unique:services,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit_rate' => ['nullable', 'numeric', 'min:0'],
            'cost_ledger_id' => ['nullable', 'exists:payment_accounts,id'],
            'revenue_ledger_id' => ['nullable', 'exists:payment_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'consumables' => ['nullable', 'array'],
            'consumables.*.product_id' => ['required', 'exists:products,id'],
            'consumables.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $service = DB::transaction(function () use ($validated, $request) {
                $service = Service::create([
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'unit_id' => $validated['unit_id'] ?? null,
                    'unit_cost' => $validated['unit_cost'] ?? 0.00,
                    'unit_rate' => $validated['unit_rate'] ?? 0.00,
                    'cost_ledger_id' => $validated['cost_ledger_id'] ?? null,
                    'revenue_ledger_id' => $validated['revenue_ledger_id'] ?? null,
                    'is_active' => $request->boolean('is_active', true),
                    'created_by' => auth()->id(),
                ]);

                if (! empty($validated['consumables'])) {
                    $grouped = [];
                    foreach ($validated['consumables'] as $item) {
                        $pId = (int) $item['product_id'];
                        $qty = (float) $item['quantity'];
                        $grouped[$pId] = ($grouped[$pId] ?? 0) + $qty;
                    }
                    foreach ($grouped as $productId => $quantity) {
                        ServiceConsumable::create([
                            'service_id' => $service->id,
                            'product_id' => $productId,
                            'quantity' => $quantity,
                        ]);
                    }
                }

                return $service;
            });

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Service created successfully.',
                    'service' => $service->load('consumables.product'),
                ]);
            }

            return redirect()->route('products.services.index')->with('success', 'Service created successfully.');
        } catch (Throwable $e) {
            Log::error('Failed to create service: '.$e->getMessage());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create service: '.$e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('error', 'Failed to create service.');
        }
    }

    /**
     * Display the specified service.
     */
    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => $service->load(['unit', 'costLedger', 'revenueLedger', 'consumables.product']),
        ]);
    }

    /**
     * Update the specified service.
     */
    public function update(Request $request, Service $service): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'unique:services,code,'.$service->id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit_rate' => ['nullable', 'numeric', 'min:0'],
            'cost_ledger_id' => ['nullable', 'exists:payment_accounts,id'],
            'revenue_ledger_id' => ['nullable', 'exists:payment_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
            'consumables' => ['nullable', 'array'],
            'consumables.*.product_id' => ['required', 'exists:products,id'],
            'consumables.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            DB::transaction(function () use ($service, $validated, $request) {
                $service->update([
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'unit_id' => $validated['unit_id'] ?? null,
                    'unit_cost' => $validated['unit_cost'] ?? 0.00,
                    'unit_rate' => $validated['unit_rate'] ?? 0.00,
                    'cost_ledger_id' => $validated['cost_ledger_id'] ?? null,
                    'revenue_ledger_id' => $validated['revenue_ledger_id'] ?? null,
                    'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $service->is_active,
                ]);

                if (isset($validated['consumables'])) {
                    $service->consumables()->delete();
                    $grouped = [];
                    foreach ($validated['consumables'] as $item) {
                        $pId = (int) $item['product_id'];
                        $qty = (float) $item['quantity'];
                        $grouped[$pId] = ($grouped[$pId] ?? 0) + $qty;
                    }
                    foreach ($grouped as $productId => $quantity) {
                        ServiceConsumable::create([
                            'service_id' => $service->id,
                            'product_id' => $productId,
                            'quantity' => $quantity,
                        ]);
                    }
                }
            });

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Service updated successfully.',
                    'service' => $service->fresh()->load('consumables.product'),
                ]);
            }

            return redirect()->route('products.services.index')->with('success', 'Service updated successfully.');
        } catch (Throwable $e) {
            Log::error('Failed to update service: '.$e->getMessage());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update service: '.$e->getMessage(),
                ], 422);
            }

            return back()->withInput()->with('error', 'Failed to update service.');
        }
    }

    /**
     * Toggle the active status of a service.
     */
    public function toggleStatus(Request $request, Service $service): JsonResponse|RedirectResponse
    {
        $service->is_active = ! $service->is_active;
        $service->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $service->is_active,
                'status_label' => $service->is_active ? 'Active' : 'Inactive',
            ]);
        }

        return back()->with('success', 'Service status updated to '.($service->is_active ? 'Active' : 'Inactive').'.');
    }

    /**
     * Remove the specified service from storage.
     */
    public function destroy(Request $request, Service $service): JsonResponse|RedirectResponse
    {
        $service->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully.',
            ]);
        }

        return redirect()->route('products.services.index')->with('success', 'Service deleted successfully.');
    }
}
