<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteDeliveryConsignmentRequest;
use App\Http\Requests\StoreDeliveryConsignmentRequest;
use App\Models\Contact;
use App\Models\DeliveryConsignment;
use App\Models\DeliveryDriver;
use App\Models\DeliveryPodCorrection;
use App\Models\DeliveryTransfer;
use App\Models\DeliveryVehicle;
use App\Services\DeliveryConsignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class DeliveryConsignmentController
 *
 * Manages dispatch consignments, proof-of-delivery (POD) documentation, customer signatures,
 * lifecycle milestone transitions (loaded -> in transit -> arrived -> delivered), and audit corrections.
 */
class DeliveryConsignmentController extends Controller
{
    /**
     * Display a filtered, paginated list of delivery consignments.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['loaded', 'in_transit', 'arrived', 'delivered', 'partial', 'failed', 'cancelled'])],
            'vehicle_id' => ['nullable', 'integer', 'exists:delivery_vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:delivery_drivers,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $visible = fn ($q) => $q->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('warehouse_id', $request->user()->locations()->pluck('locations.id')));

        $query = DeliveryConsignment::with(['customer', 'loadingTransfer.vehicle', 'loadingTransfer.driver'])
            ->whereHas('loadingTransfer', $visible);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['vehicle_id'])) {
            $query->whereHas('loadingTransfer', fn ($q) => $q->where('vehicle_id', $filters['vehicle_id']));
        }
        if (! empty($filters['driver_id'])) {
            $query->whereHas('loadingTransfer', fn ($q) => $q->where('driver_id', $filters['driver_id']));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['q'])) {
            $search = '%' . $filters['q'] . '%';
            $query->where(fn ($q) => $q->where('number', 'like', $search)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $search)));
        }

        $pendingTransfers = DeliveryTransfer::with(['vehicle.stores', 'warehouse', 'transaction', 'lines.stockItem.variant', 'lines.lots', 'lines.serials'])
            ->availableForConsignment()
            ->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('warehouse_id', $request->user()->locations()->pluck('locations.id')))
            ->latest()
            ->get()
            ->filter->hasStockAvailableForConsignment()
            ->values();

        return view('delivery.consignments', [
            'consignments' => $query->latest()->paginate(20)->withQueryString(),
            'counts' => DeliveryConsignment::whereHas('loadingTransfer', $visible)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'pendingCount' => $pendingTransfers->count(),
            'pendingTransfers' => $pendingTransfers->take(5),
            'activeDeliveries' => DeliveryConsignment::with(['customer', 'loadingTransfer.vehicle', 'loadingTransfer.driver'])
                ->whereHas('loadingTransfer', $visible)
                ->whereIn('status', DeliveryConsignment::ACTIVE_STATUSES)
                ->orderByRaw("CASE status WHEN 'arrived' THEN 1 WHEN 'in_transit' THEN 2 ELSE 3 END")
                ->latest()
                ->limit(10)
                ->get(),
            'fleetAlerts' => DeliveryVehicle::where('is_active', true)->where(function ($query) {
                $limit = today()->addDays(30)->toDateString();
                $query->whereDate('insurance_expires_at', '<=', $limit)->orWhereDate('revenue_license_expires_at', '<=', $limit);
            })->count() + DeliveryDriver::where('is_active', true)->whereDate('license_expires_at', '<=', today()->addDays(30)->toDateString())->count(),
            'vehicles' => DeliveryVehicle::orderBy('number')->get(['id', 'number']),
            'drivers' => DeliveryDriver::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show form for creating a new consignment against a loaded vehicle transfer.
     *
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        $transfers = DeliveryTransfer::with(['vehicle.stores', 'driver', 'warehouse', 'lines.stockItem.product', 'lines.stockItem.variant', 'lines.lots', 'lines.serials'])
            ->availableForConsignment()
            ->when(! auth()->user()->all_locations, fn ($q) => $q->whereIn('warehouse_id', auth()->user()->locations()->pluck('locations.id')))
            ->latest()
            ->limit(100)
            ->get()
            ->filter->hasStockAvailableForConsignment()
            ->values();

        return view('delivery.consignment-create', [
            'transfers' => $transfers,
            'customers' => Contact::whereIn('type', ['customer', 'both'])
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'shipping_address', 'address_line_1', 'address_line_2', 'city', 'state', 'country', 'zip_code']),
        ]);
    }

    /**
     * Create and record a new delivery consignment.
     *
     * @param StoreDeliveryConsignmentRequest $request
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function store(StoreDeliveryConsignmentRequest $request, DeliveryConsignmentService $service): RedirectResponse
    {
        try {
            $data = $request->validated();
            $transfer = DeliveryTransfer::findOrFail($data['loading_transfer_id']);
            $this->authorizeWarehouse($request, $transfer->warehouse_id);

            $consignment = $service->create($data, $request->user()->id);

            return redirect()->route('delivery.consignments.show', $consignment)->with('success', 'Delivery record created.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to create delivery consignment', [
                'error' => $e->getMessage(),
                'payload' => $request->except(['_token']),
            ]);

            return back()->withInput()->with('error', 'Failed to create delivery consignment: ' . $e->getMessage());
        }
    }

    /**
     * Display comprehensive details, audit trail, line items, and POD proofs of a consignment.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @return View
     */
    public function show(Request $request, DeliveryConsignment $consignment): View
    {
        $consignment->load([
            'customer',
            'cancelledBy',
            'routeStop.route',
            'corrections',
            'loadingTransfer.vehicle',
            'loadingTransfer.driver',
            'loadingTransfer.warehouse',
            'lines.damageDisposition.location',
            'lines.transferLine.stockItem.product.unit',
            'lines.transferLine.stockItem.variant',
            'lines.transferLine.lots',
            'lines.transferLine.serials',
            'lines.serialOutcomes.serial',
            'events.user',
            'proofs' => fn ($q) => $q->whereNull('deleted_at')->with('uploader'),
        ]);

        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);

        return view('delivery.consignment-show', compact('consignment'));
    }

    /**
     * Mark a loaded consignment as departed / in-transit.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function depart(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service): RedirectResponse
    {
        try {
            $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
            $service->depart($consignment, $request->user()->id);

            return back()->with('success', 'Delivery marked in transit.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to depart consignment', [
                'consignment_id' => $consignment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not depart delivery: ' . $e->getMessage());
        }
    }

    /**
     * Record driver arrival at the customer delivery destination.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function arrive(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service): RedirectResponse
    {
        try {
            $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
            $service->arrive($consignment, $request->user()->id);

            return back()->with('success', 'Customer arrival recorded.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to record arrival for consignment', [
                'consignment_id' => $consignment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not record arrival: ' . $e->getMessage());
        }
    }

    /**
     * Cancel an active consignment before delivery.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function cancel(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service): RedirectResponse
    {
        try {
            $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
            $data = $request->validate([
                'cancel_reason' => ['required', 'string', 'min:5', 'max:500'],
            ]);

            $service->cancel($consignment, $data['cancel_reason'], $request->user()->id);

            return back()->with('success', 'Delivery cancelled. Vehicle stock was not moved.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to cancel consignment', [
                'consignment_id' => $consignment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not cancel delivery: ' . $e->getMessage());
        }
    }

    /**
     * Reschedule an active consignment for a future delivery date.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function reschedule(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service): RedirectResponse
    {
        try {
            $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
            $data = $request->validate([
                'scheduled_at' => ['required', 'date', 'after:now'],
                'reason' => ['required', 'string', 'min:5', 'max:500'],
            ]);

            $service->reschedule($consignment, $data['scheduled_at'], $data['reason'], $request->user()->id);

            return back()->with('success', 'Delivery rescheduled.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to reschedule consignment', [
                'consignment_id' => $consignment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not reschedule delivery: ' . $e->getMessage());
        }
    }

    /**
     * Finalize the delivery outcome (delivered, partial, failed) and persist POD proofs.
     *
     * @param CompleteDeliveryConsignmentRequest $request
     * @param DeliveryConsignment $consignment
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function complete(
        CompleteDeliveryConsignmentRequest $request,
        DeliveryConsignment $consignment,
        DeliveryConsignmentService $service
    ): RedirectResponse {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $data = $request->validated();
        $signatureBytes = null;

        if (! empty($data['signature_data'])) {
            if (! str_starts_with($data['signature_data'], 'data:image/png;base64,')) {
                throw ValidationException::withMessages(['signature_data' => 'Invalid signature image.']);
            }
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
                    $path = 'delivery-proofs/' . $consignment->id . '/' . Str::uuid() . '.png';
                    if (! Storage::disk('local')->put($path, $signatureBytes)) {
                        throw ValidationException::withMessages(['signature_data' => 'Could not save the signature.']);
                    }
                    $savedPaths[] = $path;
                    $consignment->proofs()->create([
                        'kind' => 'signature',
                        'path' => $path,
                        'caption' => 'Receiver signature',
                        'checksum' => hash('sha256', $signatureBytes),
                        'retention_until' => now()->addYears(7),
                        'uploaded_by' => $request->user()->id,
                        'created_at' => now(),
                    ]);
                }

                foreach ($request->file('photos', []) as $photoFile) {
                    $path = $photoFile->store('delivery-proofs/' . $consignment->id, 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['photos' => 'Could not save a proof photo.']);
                    }
                    $savedPaths[] = $path;
                    $consignment->proofs()->create([
                        'kind' => 'photo',
                        'path' => $path,
                        'caption' => $data['photo_caption'] ?? null,
                        'checksum' => hash_file('sha256', $photoFile->getRealPath()),
                        'retention_until' => now()->addYears(7),
                        'uploaded_by' => $request->user()->id,
                        'created_at' => now(),
                    ]);
                }
            });

            return back()->with('success', 'Delivery outcome recorded.');
        } catch (ValidationException|HttpException $exception) {
            Storage::disk('local')->delete($savedPaths);
            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($savedPaths);
            Log::error('Failed to complete delivery consignment', [
                'consignment_id' => $consignment->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Failed to record delivery outcome: ' . $exception->getMessage());
        }
    }

    /**
     * Upload an auxiliary proof photo or receiver signature to an existing closed delivery.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @return RedirectResponse
     */
    public function uploadProof(Request $request, DeliveryConsignment $consignment): RedirectResponse
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        abort_unless(in_array($consignment->status, ['delivered', 'partial', 'failed'], true), 422);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['photo', 'signature'])],
            'caption' => ['nullable', 'string', 'max:250'],
            'image' => ['nullable', 'required_without:signature_data', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'signature_data' => ['nullable', 'required_without:image', 'string', 'max:2800000'],
        ]);

        if ($consignment->proofs()->whereNull('deleted_at')->count() >= 10) {
            throw ValidationException::withMessages(['image' => 'This delivery already has the maximum of 10 proof files.']);
        }
        if ($request->hasFile('image') && $data['kind'] !== 'photo') {
            throw ValidationException::withMessages(['kind' => 'Uploaded image files must be saved as proof photos.']);
        }
        if ($data['kind'] === 'signature' && $consignment->proofs()->where('kind', 'signature')->exists()) {
            throw ValidationException::withMessages(['signature_data' => 'This delivery already has a receiver signature.']);
        }

        if (! empty($data['signature_data'])) {
            if ($data['kind'] !== 'signature' || ! str_starts_with($data['signature_data'], 'data:image/png;base64,')) {
                throw ValidationException::withMessages(['signature_data' => 'Invalid signature image.']);
            }
            $bytes = base64_decode(substr($data['signature_data'], strlen('data:image/png;base64,')), true);
            if ($bytes === false || strlen($bytes) > 2 * 1024 * 1024 || ($info = getimagesizefromstring($bytes)) === false || $info[2] !== IMAGETYPE_PNG || $info[0] > 1600 || $info[1] > 800) {
                throw ValidationException::withMessages(['signature_data' => 'Invalid signature image.']);
            }
            $path = 'delivery-proofs/' . $consignment->id . '/' . Str::uuid() . '.png';
            if (! Storage::disk('local')->put($path, $bytes)) {
                throw ValidationException::withMessages(['image' => 'Could not save the proof image.']);
            }
        } else {
            $path = $request->file('image')->store('delivery-proofs/' . $consignment->id, 'local');
            if (! $path) {
                throw ValidationException::withMessages(['image' => 'Could not save the proof image.']);
            }
        }

        $checksum = hash('sha256', Storage::disk('local')->get($path));

        try {
            DB::transaction(function () use ($consignment, $data, $path, $checksum, $request) {
                $consignment->proofs()->create([
                    'kind' => $data['kind'],
                    'path' => $path,
                    'caption' => $data['caption'] ?? null,
                    'checksum' => $checksum,
                    'retention_until' => now()->addYears(7),
                    'uploaded_by' => $request->user()->id,
                    'created_at' => now(),
                ]);

                $consignment->events()->create([
                    'event' => 'proof_uploaded',
                    'user_id' => $request->user()->id,
                    'created_at' => now(),
                ]);
            });

            return back()->with('success', 'Proof uploaded.');
        } catch (ValidationException|HttpException $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            Log::error('Failed to upload delivery proof', [
                'consignment_id' => $consignment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Unable to upload proof. Please try again.');
        }
    }

    /**
     * Download or stream stored proof file.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @param int $proof
     * @return StreamedResponse
     */
    public function proof(Request $request, DeliveryConsignment $consignment, int $proof): StreamedResponse
    {
        $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
        $record = $consignment->proofs()->whereNull('deleted_at')->findOrFail($proof);

        return Storage::disk('local')->response($record->path);
    }

    /**
     * Soft-delete incorrect proof from operational view while retaining it for compliance audit.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @param int $proof
     * @return RedirectResponse
     */
    public function deleteProof(Request $request, DeliveryConsignment $consignment, int $proof): RedirectResponse
    {
        try {
            $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
            $record = $consignment->proofs()->whereNull('deleted_at')->findOrFail($proof);

            DB::transaction(function () use ($record, $consignment, $request) {
                // Keep the binary until retention expiry; remove it from operational POD views now.
                $record->update(['deleted_at' => now(), 'deleted_by' => $request->user()->id]);
                $consignment->events()->create([
                    'event' => 'proof_deleted',
                    'user_id' => $request->user()->id,
                    'created_at' => now(),
                ]);
            });

            return back()->with('success', 'Incorrect proof removed from the POD view and retained in the audit record.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to delete delivery proof', [
                'consignment_id' => $consignment->id,
                'proof_id' => $proof,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to remove proof.');
        }
    }

    /**
     * Request supervisory correction for an already finalized POD.
     *
     * @param Request $request
     * @param DeliveryConsignment $consignment
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function requestCorrection(Request $request, DeliveryConsignment $consignment, DeliveryConsignmentService $service): RedirectResponse
    {
        try {
            $this->authorizeWarehouse($request, $consignment->loadingTransfer->warehouse_id);
            $data = $request->validate([
                'reason' => ['required', 'string', 'min:10', 'max:500'],
            ]);

            $service->requestCorrection($consignment, $data['reason'], $request->user()->id);

            return back()->with('success', 'POD correction sent for supervisor approval.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to request POD correction', [
                'consignment_id' => $consignment->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to submit correction request.');
        }
    }

    /**
     * Supervisor approval of a POD correction, reverting stock dispositions if needed.
     *
     * @param Request $request
     * @param DeliveryPodCorrection $correction
     * @param DeliveryConsignmentService $service
     * @return RedirectResponse
     */
    public function approveCorrection(Request $request, DeliveryPodCorrection $correction, DeliveryConsignmentService $service): RedirectResponse
    {
        try {
            $correction->load('consignment.loadingTransfer');
            $this->authorizeWarehouse($request, $correction->consignment->loadingTransfer->warehouse_id);

            $service->approveCorrection($correction, $request->user()->id);

            return redirect()->route('delivery.consignments.show', $correction->consignment_id)
                ->with('success', 'POD reopened and stock reversal recorded.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to approve POD correction', [
                'correction_id' => $correction->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not approve correction: ' . $e->getMessage());
        }
    }

    /**
     * Enforce location scoping permissions on the warehouse.
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    private function authorizeWarehouse(Request $request, int $id): void
    {
        abort_unless($request->user()->all_locations || $request->user()->locations()->where('locations.id', $id)->exists(), 403);
    }
}
