<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            // Store a real date so financial-year reports can compare it safely.
            'start_date' => ['nullable', 'date'],
            'default_profit_percent' => ['required', 'numeric', 'min:-100', 'max:999999'],
            'currency' => ['required', 'string', 'max:255'],
            'currency_symbol_placement' => ['required', Rule::in(['Before amount', 'After amount'])],
            // Laravel validates against PHP's timezone database, preventing an
            // invalid value from breaking date calculations on later requests.
            'time_zone' => ['required', 'timezone:all'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'financial_year_start_month' => ['required', Rule::in([
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December',
            ])],
            'stock_accounting_method' => ['required', Rule::in([
                'FIFO (First In First Out)', 'LIFO (Last In First Out)',
            ])],
            'transaction_edit_days' => ['required', 'integer', 'min:0', 'max:36500'],
            'date_format' => ['required', Rule::in(['mm/dd/yyyy', 'dd/mm/yyyy', 'yyyy-mm-dd', 'dd-mm-yyyy'])],
            'time_format' => ['required', Rule::in(['12 Hour', '24 Hour'])],
            'currency_precision' => ['required', 'integer', 'between:0,4'],
            'quantity_precision' => ['required', 'integer', 'between:0,4'],
            'product_settings' => ['sometimes', 'array'],
            'product_settings.sku_prefix' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]*$/D'],
            'product_settings.expiry_enabled' => ['required_with:product_settings', 'boolean'],
            'product_settings.expiry_mode' => ['required_with:product_settings', Rule::in(['item_expiry', 'manufacturing_period'])],
            'product_settings.on_expiry' => ['required_with:product_settings', Rule::in(['keep_selling', 'stop_selling'])],
            'product_settings.expiry_grace_days' => ['required_with:product_settings', 'integer', 'min:0', 'max:36500'],
            'product_settings.default_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'product_settings.enable_brands' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_categories' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_subcategories' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_price_tax' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_our_price' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_sub_units' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_racks' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_row' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_position' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_warranty' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_secondary_unit' => ['required_with:product_settings', 'boolean'],
            'product_settings.enable_serial_numbers' => ['required_with:product_settings', 'boolean'],
        ];
    }
}
