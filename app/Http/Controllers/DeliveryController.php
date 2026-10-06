<?php
namespace App\Http\Controllers;

use App\Http\Requests\{SaveDeliveryDriverRequest, SaveDeliveryVehicleRequest, StoreDeliveryTransferRequest};
use App\Models\{DeliveryConsignment, DeliveryDriver, DeliveryReturn, DeliveryTransfer, DeliveryVehicle, Location};
use App\Services\{DeliveryConsignmentService, DeliveryFleetService, DeliveryInventory, DeliveryStockService};
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
        $activeDelivery = $vehicle ? DeliveryConsignment::active()->with('customer')
            ->whereHas('loadingTransfer', fn ($query) => $query->where('vehicle_id', $vehicle->id))->latest()->first() : null;
        return view('delivery.store', compact('vehicles', 'vehicle', 'products', 'balances', 'transfers', 'activeDelivery'));
    }
    public function transferForm(Request $request)
    {
        $warehouses = Location::where('is_active', true)->whereNotIn('id', DB::table('delivery_vehicle_stores')->select('location_id'))
            ->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('id', $request->user()->locations()->pluck('locations.id')))->orderBy('name')->get();
        $returnNote = null;
        $returnItems = [];
        if ($request->filled('return_id')) {
            $returnNote = DeliveryReturn::with(['consignment.loadingTransfer', 'lines.consignmentLine.transferLine.stockItem.product.unit', 'lines.consignmentLine.transferLine.stockItem.variant', 'lines.consignmentLine.transferLine.lots', 'lines.consignmentLine.transferLine.serials'])
                ->findOrFail($request->integer('return_id'));
            $this->authorizeWarehouse($request, $returnNote->consignment->loadingTransfer->warehouse_id);
            abort_unless($returnNote->status === 'pending', 422, 'This return note is already completed.');
            $request->merge(['direction' => 'unloading', 'vehicle_id' => $returnNote->consignment->loadingTransfer->vehicle_id, 'reference' => $returnNote->number]);
            foreach ($returnNote->lines as $returnLine) {
                $source = $returnLine->consignmentLine->transferLine;
                $variant = $source->stockItem->variant->first();
                $returnItems[] = [
                    'product_id' => $source->stockItem->product_id,
                    'variant_id' => $variant?->id,
                    'lot_id' => $source->lots->first()?->id,
                    'lot_number' => $source->lots->first()?->lot_number,
                    'serial_ids' => $source->serials->pluck('id')->values()->all(),
                    'serial_number' => $source->serials->pluck('serial_number')->implode(', '),
                    'product_name' => $source->stockItem->product->name,
                    'sku' => $variant?->sku ?: $source->stockItem->product->code,
                    'unit' => $source->stockItem->product->unit?->short_name,
                    'decimal' => (bool) $source->stockItem->product->unit?->allow_decimal,
                    'available' => $returnLine->quantity,
                    'quantity' => $returnLine->quantity,
                ];
            }
        }
        return view('delivery.transfer-form', ['vehicles' => DeliveryVehicle::with('drivers')->orderBy('number')->get(), 'warehouses' => $warehouses, 'returnNote' => $returnNote, 'returnItems' => $returnItems]);
    }
    public function loadingForm(Request $request)
    {
        $request->merge(['direction' => 'loading']);
        return $this->transferForm($request);
    }
    public function unloadingForm(Request $request)
    {
        $data = $request->validate(['vehicle_id' => ['nullable', 'integer', 'exists:delivery_vehicles,id'], 'delivery_id' => ['nullable', 'integer', 'exists:delivery_consignments,id']]);
        if (isset($data['delivery_id'])) {
            $consignment = DeliveryConsignment::findOrFail($data['delivery_id']);
            return app(DeliveryConsignmentController::class)->show($request, $consignment);
        }
        $query = DeliveryConsignment::with(['customer', 'loadingTransfer.vehicle'])
            ->whereIn('status', DeliveryConsignment::ACTIVE_STATUSES)
            ->whereHas('loadingTransfer', fn ($query) => $query
                ->when(isset($data['vehicle_id']), fn ($query) => $query->where('vehicle_id', $data['vehicle_id']))
                ->when(! $request->user()->all_locations, fn ($query) => $query->whereIn('warehouse_id', $request->user()->locations()->pluck('locations.id'))))
            ->latest();
        $available = (clone $query)->limit(2)->get();
        if ($available->count() === 1) {
            return app(DeliveryConsignmentController::class)->show($request, $available->first());
        }
        $consignments = $query->paginate(20);
        return view('delivery.unloading', compact('consignments'));
    }
    public function options(Request $request, DeliveryInventory $inventory)
    {
        $data = $request->validate(['vehicle_id' => ['required', 'integer', 'exists:delivery_vehicles,id'], 'warehouse_id' => ['required', 'integer', 'exists:locations,id'], 'direction' => ['required', 'in:loading,unloading'], 'q' => ['nullable', 'string', 'max:100']]);
        $this->authorizeWarehouse($request, (int) $data['warehouse_id']);
        abort_if(DB::table('delivery_vehicle_stores')->where('location_id', $data['warehouse_id'])->exists(), 422, 'Choose a warehouse.');
        $source = $data['direction'] === 'loading' ? $data['warehouse_id'] : DeliveryVehicle::findOrFail($data['vehicle_id'])->stores()->firstOrFail()->id;
        return response()->json($inventory->options($source, $data['q'] ?? '', $data['direction'] === 'loading'));
    }
    public function transfer(StoreDeliveryTransferRequest $request, DeliveryStockService $service, DeliveryConsignmentService $consignmentService)
    {
        $data = $request->validated();
        $this->authorizeWarehouse($request, (int) $data['warehouse_id']);
        $returnNote = ! empty($data['return_id']) ? DeliveryReturn::with('consignment.loadingTransfer')->findOrFail($data['return_id']) : null;
        if ($returnNote) $this->authorizeWarehouse($request, $returnNote->consignment->loadingTransfer->warehouse_id);
        $transfer = DB::transaction(function () use ($data, $request, $service, $consignmentService, $returnNote) {
            $transfer = $service->transfer($data, $request->user()->id);
            if ($returnNote) $consignmentService->completeReturn($returnNote, $transfer, $request->user()->id);
            return $transfer;
        });
        return redirect()->route('delivery.transfers.show', $transfer)->with('success', 'Stock transfer completed.');
    }
    public function receipt(Request $request, DeliveryTransfer $transfer)
    {
        $this->authorizeWarehouse($request, $transfer->warehouse_id);
        $transfer->load(['vehicle', 'driver', 'warehouse', 'transaction', 'consignment', 'lines.stockItem.product.unit', 'lines.stockItem.variant', 'lines.lots', 'lines.serials']);
        return view('delivery.receipt', compact('transfer'));
    }
    private function authorizeWarehouse(Request $request, int $id): void
    {
        abort_unless($request->user()->all_locations || $request->user()->locations()->where('locations.id', $id)->exists(), 403);
    }
}
