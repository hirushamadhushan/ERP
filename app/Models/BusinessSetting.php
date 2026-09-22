<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $fillable = [
        'business_name',
        'start_date',
        'default_profit_percent',
        'currency',
        'currency_symbol_placement',
        'time_zone',
        'logo_path',
        'financial_year_start_month',
        'stock_accounting_method',
        'transaction_edit_days',
        'date_format',
        'time_format',
        'currency_precision',
        'quantity_precision',
    ];

    protected $casts = [
        'default_profit_percent' => 'float',
        'transaction_edit_days' => 'integer',
        'currency_precision' => 'integer',
        'quantity_precision' => 'integer',
    ];

    public static function current(): self
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('business_settings')) {
                $setting = static::firstOrCreate([], [
                    'business_name' => 'Codeza POS',
                    'start_date' => '2015-01-01',
                    'default_profit_percent' => 25.00,
                    'currency' => 'Sri Lanka - Rupees(LKR)',
                    'currency_symbol_placement' => 'Before amount',
                    'time_zone' => 'Asia/Kolkata',
                    'financial_year_start_month' => 'January',
                    'stock_accounting_method' => 'FIFO (First In First Out)',
                    'transaction_edit_days' => 30,
                    'date_format' => 'mm/dd/yyyy',
                    'time_format' => '24 Hour',
                    'currency_precision' => 2,
                    'quantity_precision' => 2,
                ]);
                if (\Illuminate\Support\Facades\Schema::hasTable('business_product_settings')) {
                    $setting->productSettings()->firstOrCreate([]);
                    $setting->load('productSettings');
                }
                return $setting;
            }
        } catch (\Throwable $e) {
            // Ignore missing table error during tests
        }

        return new static([
            'business_name' => 'Codeza POS',
            'start_date' => '2015-01-01',
            'default_profit_percent' => 25.00,
            'currency' => 'Sri Lanka - Rupees(LKR)',
            'currency_symbol_placement' => 'Before amount',
            'time_zone' => 'Asia/Kolkata',
            'financial_year_start_month' => 'January',
            'stock_accounting_method' => 'FIFO (First In First Out)',
            'transaction_edit_days' => 30,
            'date_format' => 'mm/dd/yyyy',
            'time_format' => '24 Hour',
            'currency_precision' => 2,
            'quantity_precision' => 2,
        ]);
    }

    public function productSettings() { return $this->hasOne(BusinessProductSetting::class); }
}
