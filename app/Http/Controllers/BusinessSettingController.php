<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBusinessSettingsRequest;
use App\Models\BusinessSetting;
use App\Models\Unit;
use Illuminate\Http\Request;

class BusinessSettingController extends Controller
{
    public function index()
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

    public function update(SaveBusinessSettingsRequest $request)
    {
        $settings = BusinessSetting::current();
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $mime = $file->getMimeType() ?: 'image/jpeg';
            $data['logo_path'] = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));
        }
        unset($data['logo']);

        $productSettings = $data['product_settings'] ?? null;
        unset($data['product_settings']);
        if ($productSettings !== null) {
            $data['other_settings'] = array_replace($settings->other_settings ?? [], [
                'product' => $productSettings,
            ]);
        }

        $settings->update($data);

        return redirect()->route('business.settings.index')->with('status', 'Business settings updated successfully.');
    }
}
