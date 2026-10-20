<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBusinessSettingsRequest;
use App\Models\BusinessSetting;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class BusinessSettingController
 *
 * Configures global business parameters, organizational profile, currency, timezone,
 * accounting periods, and company logos.
 */
class BusinessSettingController extends Controller
{
    /**
     * Display business settings configuration panel.
     *
     * @return View
     */
    public function index(): View
    {
        $settings = BusinessSetting::current();

        $currencies = [
            'Sri Lanka - Rupees(LKR)',
            'United States - Dollar(USD)',
            'Eurozone - Euro(EUR)',
            'United Kingdom - Pound(GBP)',
            'India - Rupee(INR)',
            'Australia - Dollar(AUD)',
            'Canada - Dollar(CAD)',
            'Japan - Yen(JPY)',
            'China - Yuan(CNY)',
            'Singapore - Dollar(SGD)',
            'United Arab Emirates - Dirham(AED)',
            'Saudi Arabia - Riyal(SAR)',
            'South Korea - Won(KRW)',
            'Spain - Euro(EUR)',
            'Suriname - Dollars(SRD)',
            'Sweden - Kronor(SEK)',
            'Switzerland - Francs(CHF)',
            'New Zealand - Dollar(NZD)',
            'Mexico - Peso(MXN)',
            'Brazil - Real(BRL)',
            'South Africa - Rand(ZAR)',
            'Russian Federation - Ruble(RUB)',
            'Turkey - Lira(TRY)',
            'Pakistan - Rupee(PKR)',
            'Bangladesh - Taka(BDT)',
            'Nepal - Rupee(NPR)',
            'Thailand - Baht(THB)',
            'Malaysia - Ringgit(MYR)',
            'Indonesia - Rupiah(IDR)',
            'Philippines - Peso(PHP)',
            'Vietnam - Dong(VND)',
            'Egypt - Pound(EGP)',
            'Nigeria - Naira(NGN)',
            'Kenya - Shilling(KES)',
            'Kuwait - Dinar(KWD)',
            'Qatar - Riyal(QAR)',
            'Oman - Rial(OMR)',
            'Bahrain - Dinar(BHD)',
            'Israel - Shekel(ILS)',
        ];

        $timezones = [
            'Asia/Colombo',
            'Asia/Kolkata',
            'Asia/Kathmandu',
            'Asia/Khandyga',
            'Asia/Krasnoyarsk',
            'Asia/Kuala_Lumpur',
            'Asia/Kuching',
            'Asia/Dhaka',
            'Asia/Karachi',
            'Asia/Tokyo',
            'Asia/Singapore',
            'Asia/Bangkok',
            'Asia/Dubai',
            'Asia/Riyadh',
            'Asia/Seoul',
            'Asia/Shanghai',
            'Asia/Hong_Kong',
            'Asia/Jakarta',
            'Europe/London',
            'Europe/Paris',
            'Europe/Berlin',
            'Europe/Rome',
            'Europe/Madrid',
            'Europe/Moscow',
            'Europe/Zurich',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
            'America/Toronto',
            'America/Mexico_City',
            'America/Sao_Paulo',
            'America/Buenos_Aires',
            'Australia/Sydney',
            'Australia/Melbourne',
            'Australia/Perth',
            'Pacific/Auckland',
            'UTC',
        ];

        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];

        $units = Unit::orderBy('name')->get();

        return view('business.settings', compact('settings', 'currencies', 'timezones', 'months', 'units'));
    }

    /**
     * Update business settings and associated product configuration atomically.
     *
     * @param SaveBusinessSettingsRequest $request
     * @return RedirectResponse|JsonResponse
     */
    public function update(SaveBusinessSettingsRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $settings = BusinessSetting::current();
            $data = $request->validated();

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $mime = $file->getMimeType() ?: 'image/jpeg';
                $data['logo_path'] = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file->getRealPath()));
            }
            unset($data['logo']);

            $productSettings = $data['product_settings'] ?? null;
            unset($data['product_settings']);

            /*
             * The main settings and product defaults are edited in one form. A
             * transaction prevents a partially saved configuration if either write
             * fails, which would otherwise leave the product form out of sync.
             */
            DB::transaction(function () use ($settings, $data, $productSettings): void {
                $settings->update($data);

                if ($productSettings !== null) {
                    $settings->productSettings()->updateOrCreate([], $productSettings);
                }
            });

            if ($request->header('X-Async-Form') === '1') {
                // Return only the changed image. Re-sending an existing base64 logo
                // on every name-only save would make the AJAX response needlessly large.
                return response()->json([
                    'message' => 'Business settings updated successfully.',
                    'business' => [
                        'name' => $settings->business_name,
                        'logo' => $request->hasFile('logo') ? $settings->logo_path : null,
                    ],
                ]);
            }

            return redirect()->route('business.settings.index')->with('status', 'Business settings updated successfully.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update business settings', [
                'error' => $e->getMessage(),
            ]);

            if ($request->header('X-Async-Form') === '1') {
                return response()->json(['message' => 'Failed to update business settings.'], 500);
            }

            return back()->withInput()->with('error', 'Unable to update business settings. Please try again.');
        }
    }
}
