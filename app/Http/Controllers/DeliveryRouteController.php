<?php

namespace App\Http\Controllers;

use App\Models\DeliveryConsignment;
use App\Models\DeliveryRoute;
use App\Models\DeliveryVehicle;
use App\Services\DeliveryConsignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class DeliveryRouteController
 *
 * Coordinates consolidated multi-stop delivery routes, assigning multiple consignments
 * to an ordered vehicle itinerary and triggering sequential departures.
 */
class DeliveryRouteController extends Controller
{
    /**
     * Display multi-customer routes and pending unassigned loaded consignments.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $routes = DeliveryRoute::with(['vehicle', 'stops.consignment.customer'])->latest()->paginate(20);

        $deliveries = DeliveryConsignment::with(['customer', 'loadingTransfer.vehicle'])
            ->where('status', DeliveryConsignment::STATUS_LOADED)
            ->whereDoesntHave('routeStop')
            ->when(! $request->user()->all_locations, fn ($q) => $q->whereHas('loadingTransfer', fn ($t) => $t->whereIn('warehouse_id', $request->user()->locations()->pluck('locations.id'))))
            ->orderBy('scheduled_at')
            ->get();

        return view('delivery.routes', compact('routes', 'deliveries'));
    }

    /**
     * Build and persist a multi-customer route with sequenced stops.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $data = $request->validate([
                'consignment_ids' => ['required', 'array', 'min:2', 'max:30'],
                'consignment_ids.*' => ['integer', 'distinct', 'exists:delivery_consignments,id'],
                'scheduled_at' => ['nullable', 'date'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            DB::transaction(function () use ($data, $request) {
                $deliveries = DeliveryConsignment::with('loadingTransfer')
                    ->whereIn('id', $data['consignment_ids'])
                    ->lockForUpdate()
                    ->get();

                if ($deliveries->count() !== count($data['consignment_ids']) || $deliveries->contains(fn ($d) => $d->status !== DeliveryConsignment::STATUS_LOADED || $d->routeStop()->exists())) {
                    throw ValidationException::withMessages(['consignment_ids' => 'Choose unassigned loaded deliveries only.']);
                }

                $vehicleIds = $deliveries->pluck('loadingTransfer.vehicle_id')->unique();
                if ($vehicleIds->count() !== 1) {
                    throw ValidationException::withMessages(['consignment_ids' => 'All route stops must use the same vehicle.']);
                }

                foreach ($deliveries as $delivery) {
                    abort_unless($request->user()->all_locations || $request->user()->locations()->where('locations.id', $delivery->loadingTransfer->warehouse_id)->exists(), 403);
                }

                $route = DeliveryRoute::create([
                    'number' => 'ROUTE-PENDING-' . uniqid(),
                    'vehicle_id' => $vehicleIds->first(),
                    'scheduled_at' => $data['scheduled_at'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $route->update(['number' => 'RT-' . str_pad((string) $route->id, 6, '0', STR_PAD_LEFT)]);

                foreach ($data['consignment_ids'] as $index => $id) {
                    $route->stops()->create([
                        'consignment_id' => $id,
                        'sequence' => $index + 1,
                        'status' => 'loaded',
                    ]);
                }
            });

            return back()->with('success', 'Multi-customer route created.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to create delivery route', [
                'error' => $e->getMessage(),
                'payload' => $request->except(['_token']),
            ]);

            return back()->withInput()->with('error', 'Failed to create route: ' . $e->getMessage());
        }
    }

    /**
     * Depart an entire route and simultaneously mark all associated stops as in-transit.
     *
     * @param Request $request
     * @param DeliveryRoute $route
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function depart(Request $request, DeliveryRoute $route, DeliveryConsignmentService $service): RedirectResponse
    {
        try {
            $route->load('stops.consignment.loadingTransfer');

            if ($route->status !== 'planned') {
                throw ValidationException::withMessages(['route' => 'Only a planned route can depart.']);
            }

            foreach ($route->stops as $stop) {
                abort_unless($request->user()->all_locations || $request->user()->locations()->where('locations.id', $stop->consignment->loadingTransfer->warehouse_id)->exists(), 403);
                $service->depart($stop->consignment, $request->user()->id);
            }

            return back()->with('success', 'Route departed with all customer stops.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to depart delivery route', [
                'route_id' => $route->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to depart route: ' . $e->getMessage());
        }
    }
}
