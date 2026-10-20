<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDeliveryDriverRequest;
use App\Http\Requests\SaveDeliveryVehicleRequest;
use App\Http\Requests\StoreDeliveryTransferRequest;
use App\Models\DeliveryConsignment;
use App\Models\DeliveryDriver;
use App\Models\DeliveryTransfer;
use App\Models\DeliveryVehicle;
use App\Models\Location;
use App\Services\DeliveryFleetService;
use App\Services\DeliveryInventory;
use App\Services\DeliveryStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class DeliveryController
 *
 * Coordinates delivery fleet resources (vehicles, drivers) and mobile stock replenishment
 * between fixed brick-and-mortar warehouses and vehicle store locations.
 */
class DeliveryController extends Controller
{
    /**
     * Display a paginated list of delivery vehicles.
     *
     * @param Request $request
     * @return View
     */
    public function vehicles(Request $request): View
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';

        $vehicles = DeliveryVehicle::with('drivers')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%' . $search . '%')->orWhere('name', 'like', '%' . $search . '%')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('delivery.vehicles', compact('vehicles'));
    }

    /**
     * Display the vehicle creation or edit form.
     *
     * @param DeliveryVehicle|null $vehicle
     * @return View
     */
    public function vehicleForm(?DeliveryVehicle $vehicle = null): View
    {
        return view('delivery.vehicle-form', [
            'vehicle' => $vehicle ?? new DeliveryVehicle(['is_active' => true]),
        ]);
    }

    /**
     * Save (create or update) a delivery fleet vehicle.
     *
     * @param SaveDeliveryVehicleRequest $request
     * @param DeliveryFleetService $service
     * @param DeliveryVehicle|null $vehicle
     * @return RedirectResponse
     */
    public function saveVehicle(
        SaveDeliveryVehicleRequest $request,
        DeliveryFleetService $service,
        ?DeliveryVehicle $vehicle = null
    ): RedirectResponse {
        try {
            $service->saveVehicle($vehicle ?? new DeliveryVehicle, $request->validated());

            return redirect()->route('delivery.vehicles.index')->with('success', 'Vehicle saved.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to save delivery vehicle', [
                'vehicle_id' => $vehicle?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Unable to save vehicle details. Please try again.');
        }
    }

    /**
     * Display a paginated list of delivery drivers.
     *
     * @param Request $request
     * @return View
     */
    public function drivers(Request $request): View
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';

        $drivers = DeliveryDriver::with('vehicles')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%' . $search . '%')->orWhere('license_number', 'like', '%' . $search . '%')->orWhere('phone', 'like', '%' . $search . '%')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $vehicles = DeliveryVehicle::with('drivers')->orderBy('number')->get();

        return view('delivery.drivers', compact('drivers', 'vehicles'));
    }

    /**
     * Display the driver creation or edit form.
     *
     * @param DeliveryDriver|null $driver
     * @return View
     */
    public function driverForm(?DeliveryDriver $driver = null): View
    {
        return view('delivery.driver-form', [
            'driver' => $driver ?? new DeliveryDriver(['is_active' => true]),
            'vehicles' => DeliveryVehicle::with('drivers')->orderBy('number')->get(),
        ]);
    }

    /**
     * Save (create or update) a driver profile and vehicle assignments.
     *
     * @param SaveDeliveryDriverRequest $request
     * @param DeliveryFleetService $service
     * @param DeliveryDriver|null $driver
     * @return RedirectResponse
     */
    public function saveDriver(
        SaveDeliveryDriverRequest $request,
        DeliveryFleetService $service,
        ?DeliveryDriver $driver = null
    ): RedirectResponse {
        try {
            $service->saveDriver($driver ?? new DeliveryDriver, $request->validated(), $request->user()->id);

            return redirect()->route('delivery.drivers.index')->with('success', 'Driver and vehicle assignment saved.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to save delivery driver', [
                'driver_id' => $driver?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Unable to save driver details. Please try again.');
        }
    }

    /**
     * Display vehicle mobile warehouse inventory balances and transfer history.
     *
     * @param Request $request
     * @param DeliveryInventory $inventory
     * @return View
     */
    public function store(Request $request, DeliveryInventory $inventory): View
    {
        $data = $request->validate([
            'vehicle_id' => ['nullable', 'integer', 'exists:delivery_vehicles,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $vehicles = DeliveryVehicle::with('drivers')->orderBy('number')->get();
        $vehicle = isset($data['vehicle_id']) ? DeliveryVehicle::with(['stores', 'drivers'])->findOrFail($data['vehicle_id']) : null;
        $products = $vehicle ? $inventory->products($vehicle->stores->first()->id, $data['q'] ?? '')->paginate(20, ['*'], 'stock_page')->withQueryString() : null;
        $balances = $products ? $inventory->balances($products->getCollection(), $vehicle->stores->first()->id) : [];
        $transfers = DeliveryTransfer::with(['vehicle', 'driver', 'warehouse', 'transaction'])
            ->when($vehicle, fn ($q) => $q->where('vehicle_id', $vehicle->id))
            ->latest()
            ->paginate(20, ['*'], 'history_page')
            ->withQueryString();

        $activeDelivery = $vehicle ? DeliveryConsignment::active()->with('customer')
            ->whereHas('loadingTransfer', fn ($query) => $query->where('vehicle_id', $vehicle->id))->latest()->first() : null;

        $vehicleDeparted = $vehicle ? DeliveryConsignment::whereIn('status', [DeliveryConsignment::STATUS_IN_TRANSIT, DeliveryConsignment::STATUS_ARRIVED])
            ->whereHas('loadingTransfer', fn ($query) => $query->where('vehicle_id', $vehicle->id))->exists() : false;

        return view('delivery.store', compact('vehicles', 'vehicle', 'products', 'balances', 'transfers', 'activeDelivery', 'vehicleDeparted'));
    }

    /**
     * Show vehicle stock loading or unloading transfer form.
     *
     * @param Request $request
     * @return View
     */
    public function transferForm(Request $request): View
    {
        if ($request->input('direction') === DeliveryTransfer::DIRECTION_UNLOADING) {
            $this->authorizeTransferDirection($request, DeliveryTransfer::DIRECTION_UNLOADING);
        }

        $warehouses = Location::where('is_active', true)
            ->whereNotIn('id', DB::table('delivery_vehicle_stores')->select('location_id'))
            ->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('id', $request->user()->locations()->pluck('locations.id')))
            ->orderBy('name')
            ->get();

        return view('delivery.transfer-form', [
            'vehicles' => DeliveryVehicle::with('drivers')->orderBy('number')->get(),
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Alias for warehouse to vehicle loading form.
     *
     * @param Request $request
     * @return View
     */
    public function loadingForm(Request $request): View
    {
        $request->merge(['direction' => 'loading']);
        return $this->transferForm($request);
    }

    /**
     * Alias for vehicle to warehouse unloading form.
     *
     * @param Request $request
     * @return View
     */
    public function unloadingForm(Request $request): View
    {
        // Unloading is one stock-transfer screen: vehicle stock goes directly to a warehouse.
        $request->merge(['direction' => 'unloading']);
        return $this->transferForm($request);
    }

    /**
     * Retrieve live stock options available for loading/unloading transfers.
     *
     * @param Request $request
     * @param DeliveryInventory $inventory
     * @return JsonResponse
     */
    public function options(Request $request, DeliveryInventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:delivery_vehicles,id'],
            'warehouse_id' => ['nullable', 'required_if:direction,loading', 'integer', 'exists:locations,id'],
            'direction' => ['required', 'in:loading,unloading'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $this->authorizeTransferDirection($request, $data['direction']);

        if (! empty($data['warehouse_id'])) {
            $this->authorizeWarehouse($request, (int) $data['warehouse_id']);
            abort_if(DB::table('delivery_vehicle_stores')->where('location_id', $data['warehouse_id'])->exists(), 422, 'Choose a warehouse.');
        }

        $source = $data['direction'] === 'loading'
            ? $data['warehouse_id']
            : DeliveryVehicle::findOrFail($data['vehicle_id'])->stores()->firstOrFail()->id;

        return response()->json($inventory->options($source, $data['q'] ?? '', $data['direction'] === 'loading'));
    }

    /**
     * Execute an atomic vehicle inventory stock transfer.
     *
     * @param StoreDeliveryTransferRequest $request
     * @param DeliveryStockService $service
     * @return RedirectResponse
     */
    public function transfer(StoreDeliveryTransferRequest $request, DeliveryStockService $service): RedirectResponse
    {
        try {
            $data = $request->validated();
            $this->authorizeTransferDirection($request, $data['direction']);
            $this->authorizeWarehouse($request, (int) $data['warehouse_id']);

            $transfer = $service->transfer($data, $request->user()->id);

            return redirect()->route('delivery.transfers.show', $transfer)->with('success', 'Stock transfer completed.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to execute stock transfer', [
                'error' => $e->getMessage(),
                'payload' => $request->except(['_token']),
            ]);

            return back()->withInput()->with('error', 'Stock transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Display printable transfer receipt document.
     *
     * @param Request $request
     * @param DeliveryTransfer $transfer
     * @return View
     */
    public function receipt(Request $request, DeliveryTransfer $transfer): View
    {
        $this->authorizeWarehouse($request, $transfer->warehouse_id);

        $transfer->load([
            'vehicle',
            'driver',
            'warehouse',
            'transaction.creator',
            'consignment',
            'lines.stockItem.product.unit',
            'lines.stockItem.variant',
            'lines.lots',
            'lines.serials',
        ]);

        return view('delivery.receipt', compact('transfer'));
    }

    /**
     * Authorize user access to the designated warehouse location.
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    private function authorizeWarehouse(Request $request, int $id): void
    {
        abort_unless($request->user()->all_locations || $request->user()->locations()->where('locations.id', $id)->exists(), 403);
    }

    /**
     * Ensure the user possesses explicit permission for the requested transfer direction.
     *
     * @param Request $request
     * @param string $direction
     * @return void
     */
    private function authorizeTransferDirection(Request $request, string $direction): void
    {
        $permission = $direction === DeliveryTransfer::DIRECTION_UNLOADING
            ? 'delivery.unload'
            : 'delivery.transfer';

        abort_unless($request->user()->canUseDelivery($permission), 403);
    }
}
