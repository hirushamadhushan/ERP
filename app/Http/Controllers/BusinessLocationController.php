<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBusinessLocationRequest;
use App\Http\Requests\SaveLocationReceiptSettingsRequest;
use App\Models\InvoiceLayout;
use App\Models\InvoiceScheme;
use App\Models\Location;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ReceiptPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BusinessLocationController extends Controller
{
    private const PAYMENT_METHODS = [
        'cash' => 'Cash', 'card' => 'Card', 'cheque' => 'Cheque', 'bank_transfer' => 'Bank Transfer', 'other' => 'Other',
        'custom_payment_1' => 'Custom Payment 1', 'custom_payment_2' => 'Custom Payment 2',
        'custom_payment_3' => 'Custom Payment 3', 'custom_payment_4' => 'Custom Payment 4',
        'custom_payment_5' => 'Custom Payment 5', 'custom_payment_6' => 'Custom Payment 6',
        'custom_payment_7' => 'Custom Payment 7',
    ];

    public function index(): View
    {
        return view('business.locations', [
            'locations' => Location::with(['invoiceScheme', 'invoiceLayoutPos', 'invoiceLayoutSale', 'contacts', 'customFieldValues', 'featuredProducts', 'paymentMethods'])->orderBy('name')->get(),
            'invoiceSchemes' => InvoiceScheme::orderByDesc('is_default')->orderBy('name')->get(),
            'invoiceLayouts' => InvoiceLayout::orderByDesc('is_default')->orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(['id', 'name', 'code']),
            'paymentAccounts' => PaymentAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'account_number']),
            'paymentMethodLabels' => self::PAYMENT_METHODS,
        ]);
    }

    public function store(SaveBusinessLocationRequest $request): RedirectResponse
    {
        $this->saveLocation(new Location(), $request->validated());
        return redirect()->route('business.locations.index')->with('status', 'Business location added successfully.');
    }

    public function update(SaveBusinessLocationRequest $request, Location $location): RedirectResponse
    {
        $this->saveLocation($location, $request->validated());
        return redirect()->route('business.locations.index')->with('status', 'Business location updated successfully.');
    }

    public function toggle(Location $location): JsonResponse
    {
        $location->update(['is_active' => ! $location->is_active]);
        return response()->json(['ok' => true, 'is_active' => $location->is_active, 'message' => $location->is_active ? 'Business location activated.' : 'Business location deactivated.']);
    }

    public function checkCode(): JsonResponse
    {
        $code = trim((string) request('code'));
        $ignoreId = request()->integer('location_id');
        $exists = $code !== '' && Location::where('code', $code)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists();
        return response()->json(['available' => ! $exists]);
    }

    public function settings(Location $location): View
    {
        return view('business.location-settings', [
            'location' => $location,
            'setting' => $location->receiptSetting ?? new \App\Models\LocationReceiptSetting(['auto_print_invoice' => true, 'printer_type' => 'browser', 'invoice_layout_id' => $location->invoice_layout_pos_id, 'invoice_scheme_id' => $location->invoice_scheme_id]),
            'printers' => ReceiptPrinter::where('is_active', true)->orderBy('name')->get(),
            'layouts' => InvoiceLayout::orderByDesc('is_default')->orderBy('name')->get(),
            'schemes' => InvoiceScheme::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function updateSettings(SaveLocationReceiptSettingsRequest $request, Location $location): RedirectResponse
    {
        $data = $request->validated();
        if ($data['printer_type'] === 'browser') $data['receipt_printer_id'] = null;
        $location->receiptSetting()->updateOrCreate([], $data);
        return back()->with('status', 'Location receipt settings updated successfully.');
    }

    private function saveLocation(Location $location, array $data): void
    {
        DB::transaction(function () use ($location, $data) {
            $contacts = $data['contacts'] ?? [];
            $customFields = $data['custom_fields'] ?? [];
            $featuredProducts = $data['featured_product_ids'] ?? [];
            $paymentMethods = $data['payment_methods'] ?? [];
            unset($data['contacts'], $data['custom_fields'], $data['featured_product_ids'], $data['payment_methods']);
            $data['code'] = $data['code'] ?: $this->nextCode();
            $data['location_code'] = $data['code'];
            $location->fill($data)->save();

            $location->contacts()->delete();
            foreach ($contacts as $type => $value) if (filled($value)) $location->contacts()->create(['type' => $type, 'value' => trim($value)]);
            $location->customFieldValues()->delete();
            foreach ($customFields as $number => $value) if (filled($value)) $location->customFieldValues()->create(['field_number' => $number + 1, 'value' => trim($value)]);
            $location->featuredProducts()->sync(collect($featuredProducts)->values()->mapWithKeys(fn ($id, $order) => [$id => ['display_order' => $order]])->all());
            $location->paymentMethods()->delete();
            foreach (self::PAYMENT_METHODS as $method => $label) {
                $setting = $paymentMethods[$method] ?? [];
                $location->paymentMethods()->create(['method' => $method, 'is_enabled' => (bool) ($setting['enabled'] ?? false), 'payment_account_id' => $setting['account_id'] ?? null]);
            }
        });
    }

    private function nextCode(): string
    {
        $number = (int) Location::lockForUpdate()->selectRaw("MAX(CAST(SUBSTRING(code, 3) AS UNSIGNED)) AS value")->value('value');
        return 'BL'.str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
    }
}
