<?php

namespace App\Http\Controllers;

use App\Http\Requests\{CompleteDeliveryConsignmentRequest, StoreDeliveryConsignmentRequest};
use App\Models\{Contact, DeliveryConsignment, DeliveryDriver, DeliveryPodCorrection, DeliveryTransfer, DeliveryVehicle};
use App\Services\DeliveryConsignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class DeliveryConsignmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['loaded', 'in_transit', 'arrived', 'delivered', 'partial', 'failed', 'cancelled'])],
            'vehicle_id' => ['nullable', 'integer', 'exists:delivery_vehicles,id'], 'driver_id' => ['nullable', 'integer', 'exists:delivery_drivers,id'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $visible = fn ($q) => $q->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('warehouse_id', $request->user()->locations()->pluck('locations.id')));
        $query = DeliveryConsignment::with(['customer', 'loadingTransfer.vehicle', 'loadingTransfer.driver'])->whereHas('loadingTransfer', $visible);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['vehicle_id'])) $query->whereHas('loadingTransfer', fn ($q) => $q->where('vehicle_id', $filters['vehicle_id']));
        if (! empty($filters['driver_id'])) $query->whereHas('loadingTransfer', fn ($q) => $q->where('driver_id', $filters['driver_id']));
        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);
        if (! empty($filters['q'])) {
            $search = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('number', 'like', $search)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $search)));
        }
        $pendingTransfers = DeliveryTransfer::with(['vehicle.stores', 'warehouse', 'transaction', 'lines.stockItem.variant', 'lines.lots', 'lines.serials'])
            ->availableForConsignment()->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('warehouse_id', $request->user()->locations()->pluck('locations.id')))
            ->latest()->get()->filter->hasStockAvailableForConsignment()->values();
        return view('delivery.consignments', [
            'consignments' => $query->latest()->paginate(20)->withQueryString(),
            'counts' => DeliveryConsignment::whereHas('loadingTransfer', $visible)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'pendingCount' => $pendingTransfers->count(),
            'pendingTransfers' => $pendingTransfers->take(5),
            'activeDeliveries' => DeliveryConsignment::with(['customer', 'loadingTransfer.vehicle', 'loadingTransfer.driver'])
                ->whereHas('loadingTransfer', $visible)->whereIn('status', DeliveryConsignment::ACTIVE_STATUSES)
                ->orderByRaw("CASE status WHEN 'arrived' THEN 1 WHEN 'in_transit' THEN 2 ELSE 3 END")
                ->latest()->limit(10)->get(),
            'fleetAlerts' => DeliveryVehicle::where('is_active', true)->where(function ($query) {
                $limit = today()->addDays(30)->toDateString();
                $query->whereDate('insurance_expires_at', '<=', $limit)->orWhereDate('revenue_license_expires_at', '<=', $limit);
            })->count() + DeliveryDriver::where('is_active', true)->whereDate('license_expires_at', '<=', today()->addDays(30)->toDateString())->count(),
            'vehicles' => DeliveryVehicle::orderBy('number')->get(['id', 'number']),
            'drivers' => DeliveryDriver::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $transfers = DeliveryTransfer::with(['vehicle.stores', 'driver', 'warehouse', 'lines.stockItem.product', 'lines.stockItem.variant', 'lines.lots', 'lines.serials'])
            ->availableForConsignment()
            ->when(! auth()->user()->all_locations, fn ($q) => $q->whereIn('warehouse_id', auth()->user()->locations()->pluck('locations.id')))
            ->latest()->limit(100)->get()->filter->hasStockAvailableForConsignment()->values();
        return view('delivery.consignment-create', [
            'transfers' => $transfers,
            'customers' => Contact::whereIn('type', ['customer', 'both'])->where('status', 'active')->orderBy('name')
                ->get(['id', 'name', 'shipping_address', 'address_line_1', 'address_line_2', 'city', 'state', 'country', 'zip_code']),
        ]);
    }

    public function store(StoreDeliveryConsignmentRequest $request, DeliveryConsignmentService $service)
    {
        $data = $request->validated();
        $transfer = DeliveryTransfer::findOrFail($data['loading_transfer_id']);
        $this->authorizeWarehouse($request, $transfer->warehouse_id);
        $consignment = $service->create($data, $request->user()->id);
        return redirect()->route('delivery.consignments.show', $consignment)->with('success', 'Delivery record created.');
    }

    public function show(Request $request, DeliveryConsignment $consignment)
    {
        $consignment->load(['customer', 'cancelledBy', 'routeStop.route', 'corrections', 'loadingTransfer.vehicle', 'loadingTransfer.driver', 'loadingTransfer.warehouse', 'lines.damageDisposition.location', 'lines.transferLine.stockItem.product.unit', 'lines.transferLine.stockItem.variant', 'lines.transferLine.lots', 'lines.transferLine.serials', 'lines.serialOutcomes.serial', 'events.user', 'proofs' => fn($q) => $q->whereNull('deleted_at')->with('uploader')]);
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        return view('delivery.consignment-show', compact('consignment'));
    }

    public function depart(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $service->depart($consignment, $request->user()->id);
        return back()->with('success', 'Delivery marked in transit.');
    }

    public function arrive(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $service->arrive($consignment, $request->user()->id);
        return back()->with('success', 'Customer arrival recorded.');
    }

    public function cancel(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->cancel($consignment, $data['cancel_reason'], $request->user()->id);
        return back()->with('success', 'Delivery cancelled. Vehicle stock was not moved.');
    }

    public function reschedule(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $data = $request->validate(['scheduled_at' => ['required', 'date', 'after:now'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->reschedule($consignment, $data['scheduled_at'], $data['reason'], $request->user()->id);
        return back()->with('success', 'Delivery rescheduled.');
    }

    public function complete(CompleteDeliveryConsignmentRequest $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $data = $request->validated();
        $signatureBytes = null;
        if (! empty($data['signature_data'])) {
            if (! str_starts_with($data['signature_data'], 'data:image/png;base64,')) throw ValidationException::withMessages(['signature_data' => 'Invalid signature image.']);
            $signatureBytes = base64_decode(substr($data['signature_data'], strlen('data:image/png;base64,')), true);
            if ($signatureBytes === false || strlen($signatureBytes) > 2 * 1024 * 1024 || ($info = getimagesizefromstring($signatureBytes)) === false || $info[2] !== IMAGETYPE_PNG || $info[0] > 1600 || $info[1] > 800) {
                throw ValidationException::withMessages(['signature_data' => 'Invalid signature image.']);
            }
        }
        $savedPaths = [];
        try {
            DB::transaction(function () use ($service, $consignment, $data, $request, $signatureBytes, &$savedPaths) {
                $service->complete($consignment, $data, $request->user()->id);
                if ($signatureBytes !== null) {
                    $path = 'delivery-proofs/'.$consignment->id.'/'.Str::uuid().'.png';
                    if (! Storage::disk('local')->put($path, $signatureBytes)) throw ValidationException::withMessages(['signature_data' => 'Could not save the signature.']);
                    $savedPaths[] = $path;
                    $consignment->proofs()->create(['kind' => 'signature', 'path' => $path, 'caption' => 'Receiver signature', 'checksum' => hash('sha256', $signatureBytes), 'retention_until' => now()->addYears(7), 'uploaded_by' => $request->user()->id, 'created_at' => now()]);
                }
                foreach ($request->file('photos', []) as $photoFile) {
                    $path = $photoFile->store('delivery-proofs/'.$consignment->id, 'local');
                    if (! $path) throw ValidationException::withMessages(['photos' => 'Could not save a proof photo.']);
                    $savedPaths[] = $path;
                    $consignment->proofs()->create(['kind' => 'photo', 'path' => $path, 'caption' => $data['photo_caption'] ?? null, 'checksum' => hash_file('sha256', $photoFile->getRealPath()), 'retention_until' => now()->addYears(7), 'uploaded_by' => $request->user()->id, 'created_at' => now()]);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($savedPaths);
            throw $exception;
        }
        return back()->with('success', 'Delivery outcome recorded.');
    }

    public function uploadProof(Request $request, DeliveryConsignment $consignment)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        abort_unless(in_array($consignment->status, ['delivered', 'partial', 'failed'], true), 422);
        $data = $request->validate(['kind' => ['required', Rule::in(['photo', 'signature'])], 'caption' => ['nullable','string','max:250'], 'image' => ['nullable', 'required_without:signature_data', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'signature_data' => ['nullable', 'required_without:image', 'string', 'max:2800000']]);
        if ($consignment->proofs()->whereNull('deleted_at')->count() >= 10) throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'This delivery already has the maximum of 10 proof files.']);
        if ($request->hasFile('image') && $data['kind'] !== 'photo') throw ValidationException::withMessages(['kind' => 'Uploaded image files must be saved as proof photos.']);
        if ($data['kind'] === 'signature' && $consignment->proofs()->where('kind', 'signature')->exists()) throw ValidationException::withMessages(['signature_data' => 'This delivery already has a receiver signature.']);
        if (! empty($data['signature_data'])) {
            if ($data['kind'] !== 'signature' || ! str_starts_with($data['signature_data'], 'data:image/png;base64,')) throw \Illuminate\Validation\ValidationException::withMessages(['signature_data' => 'Invalid signature image.']);
            $bytes = base64_decode(substr($data['signature_data'], strlen('data:image/png;base64,')), true);
            if ($bytes === false || strlen($bytes) > 2 * 1024 * 1024 || ($info = getimagesizefromstring($bytes)) === false || $info[2] !== IMAGETYPE_PNG || $info[0] > 1600 || $info[1] > 800) throw \Illuminate\Validation\ValidationException::withMessages(['signature_data' => 'Invalid signature image.']);
            $path = 'delivery-proofs/'.$consignment->id.'/'.\Illuminate\Support\Str::uuid().'.png';
            if (! Storage::disk('local')->put($path, $bytes)) throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'Could not save the proof image.']);
        } else {
            $path = $request->file('image')->store('delivery-proofs/'.$consignment->id, 'local');
            if (! $path) throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'Could not save the proof image.']);
        }
        $checksum = hash('sha256', Storage::disk('local')->get($path));
        try { \Illuminate\Support\Facades\DB::transaction(function () use ($consignment, $data, $path, $checksum, $request) {
            $consignment->proofs()->create(['kind' => $data['kind'], 'path' => $path, 'caption' => $data['caption'] ?? null, 'checksum' => $checksum, 'retention_until' => now()->addYears(7), 'uploaded_by' => $request->user()->id, 'created_at' => now()]);
            $consignment->events()->create(['event' => 'proof_uploaded', 'user_id' => $request->user()->id, 'created_at' => now()]);
        }); }
        catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        return back()->with('success', 'Proof uploaded.');
    }

    public function proof(Request $request, DeliveryConsignment $consignment, int $proof)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $record = $consignment->proofs()->whereNull('deleted_at')->findOrFail($proof);
        return Storage::disk('local')->response($record->path);
    }

    public function deleteProof(Request $request, DeliveryConsignment $consignment, int $proof)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $record = $consignment->proofs()->whereNull('deleted_at')->findOrFail($proof);
        DB::transaction(function () use ($record, $consignment, $request) {
            // Keep the binary until retention expiry; remove it from operational POD views now.
            $record->update(['deleted_at' => now(), 'deleted_by' => $request->user()->id]);
            $consignment->events()->create(['event'=>'proof_deleted','user_id'=>$request->user()->id,'created_at'=>now()]);
        });
        return back()->with('success', 'Incorrect proof removed from the POD view and retained in the audit record.');
    }

    public function requestCorrection(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service)
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $data=$request->validate(['reason'=>['required','string','min:10','max:500']]);
        $service->requestCorrection($consignment,$data['reason'],$request->user()->id);
        return back()->with('success','POD correction sent for supervisor approval.');
    }

    public function approveCorrection(Request $request, DeliveryPodCorrection $correction, DeliveryConsignmentService $service)
    {
        $correction->load('consignment.loadingTransfer');
        $this->authorizeWarehouse($request,$correction->consignment->loadingTransfer->warehouse_id);
        $service->approveCorrection($correction,$request->user()->id);
        return redirect()->route('delivery.consignments.show',$correction->consignment_id)->with('success','POD reopened and stock reversal recorded.');
    }

    private function authorizeWarehouse(Request $request, int $id): void
    {
        abort_unless($request->user()->all_locations || $request->user()->locations()->where('locations.id', $id)->exists(), 403);
    }
}
