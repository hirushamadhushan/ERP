<?php
namespace App\Http\Controllers;

use App\Http\Requests\{SaveDeliveryDriverRequest, SaveDeliveryVehicleRequest, StoreDeliveryTransferRequest};
use App\Models\{DeliveryDriver, DeliveryTransfer, DeliveryVehicle, Location};
use App\Services\{DeliveryFleetService, DeliveryInventory, DeliveryStockService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function vehicles(Request $request)
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';
        $vehicles = DeliveryVehicle::with('drivers')->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))->latest()->paginate(20)->withQueryString();
        return view('delivery.vehicles', compact('vehicles'));
    }
    public function vehicleForm(?DeliveryVehicle $vehicle = null) { return view('delivery.vehicle-form', ['vehicle' => $vehicle ?? new DeliveryVehicle(['is_active' => true])]); }
    public function saveVehicle(SaveDeliveryVehicleRequest $request, DeliveryFleetService $service, ?DeliveryVehicle $vehicle = null)
    {
        $service->saveVehicle($vehicle ?? new DeliveryVehicle, $request->validated());
        return redirect()->route('delivery.vehicles.index')->with('success', 'Vehicle saved.');
    }
    public function drivers(Request $request)
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';
        $drivers = DeliveryDriver::with('vehicles')->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('license_number', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')))->latest()->paginate(20)->withQueryString();
        $vehicles = DeliveryVehicle::with('drivers')->orderBy('number')->get();

        return view('delivery.drivers', compact('drivers', 'vehicles'));
    }
    public function driverForm(?DeliveryDriver $driver = null)
    {
        return view('delivery.driver-form', ['driver' => $driver ?? new DeliveryDriver(['is_active' => true]), 'vehicles' => DeliveryVehicle::with('drivers')->orderBy('number')->get()]);
    }
    public function saveDriver(SaveDeliveryDriverRequest $request, DeliveryFleetService $service, ?DeliveryDriver $driver = null)
    {
        $service->saveDriver($driver ?? new DeliveryDriver, $request->validated(), $request->user()->id);
        return redirect()->route('delivery.drivers.index')->with('success', 'Driver and vehicle assignment saved.');
    }
    public function store(Request $request, DeliveryInventory $inventory)
    {
        $data = $request->validate(['vehicle_id' => ['nullable', 'integer', 'exists:delivery_vehicles,id'], 'q' => ['nullable', 'string', 'max:100']]);
        $vehicles = DeliveryVehicle::with('drivers')->orderBy('number')->get();
        $vehicle = isset($data['vehicle_id']) ? DeliveryVehicle::with(['stores', 'drivers'])->findOrFail($data['vehicle_id']) : null;
        $products = $vehicle ? $inventory->products($vehicle->stores->first()->id, $data['q'] ?? '')->paginate(20, ['*'], 'stock_page')->withQueryString() : null;
        $balances = $products ? $inventory->balances($products->getCollection(), $vehicle->stores->first()->id) : [];
        $transfers = DeliveryTransfer::with(['vehicle', 'driver', 'warehouse', 'transaction'])->when($vehicle, fn ($q) => $q->where('vehicle_id', $vehicle->id))->latest()->paginate(20, ['*'], 'history_page')->withQueryString();
        return view('delivery.store', compact('vehicles', 'vehicle', 'products', 'balances', 'transfers'));
    }
    public function transferForm(Request $request)
    {
        $warehouses = Location::where('is_active', true)->whereNotIn('id', DB::table('delivery_vehicle_stores')->select('location_id'))
            ->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('id', $request->user()->locations()->pluck('locations.id')))->orderBy('name')->get();
        return view('delivery.transfer-form', ['vehicles' => DeliveryVehicle::with('drivers')->orderBy('number')->get(), 'warehouses' => $warehouses]);
    }
    public function options(Request $request, DeliveryInventory $inventory)
    {
        $data = $request->validate(['vehicle_id' => ['required', 'integer', 'exists:delivery_vehicles,id'], 'warehouse_id' => ['required', 'integer', 'exists:locations,id'], 'direction' => ['required', 'in:loading,unloading'], 'q' => ['nullable', 'string', 'max:100']]);
        $this->authorizeWarehouse($request, (int) $data['warehouse_id']);
        abort_if(DB::table('delivery_vehicle_stores')->where('location_id', $data['warehouse_id'])->exists(), 422, 'Choose a warehouse.');
        $source = $data['direction'] === 'loading' ? $data['warehouse_id'] : DeliveryVehicle::findOrFail($data['vehicle_id'])->stores()->firstOrFail()->id;
        return response()->json($inventory->options($source, $data['q'] ?? ''));
    }
    public function transfer(StoreDeliveryTransferRequest $request, DeliveryStockService $service)
    {
        $this->authorizeWarehouse($request, (int) $request->validated('warehouse_id'));
        $transfer = $service->transfer($request->validated(), $request->user()->id);
        return redirect()->route('delivery.transfers.show', $transfer)->with('success', 'Stock transfer completed.');
    }
    public function receipt(DeliveryTransfer $transfer)
    {
        $transfer->load(['vehicle', 'driver', 'warehouse', 'transaction', 'lines.stockItem.product.unit', 'lines.stockItem.variant', 'lines.lots', 'lines.serials']);
        return view('delivery.receipt', compact('transfer'));
    }
    private function authorizeWarehouse(Request $request, int $id): void
    {
        abort_unless($request->user()->all_locations || $request->user()->locations()->where('locations.id', $id)->exists(), 403);
    }
}
