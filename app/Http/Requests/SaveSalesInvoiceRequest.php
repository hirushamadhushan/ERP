<?php

namespace App\Http\Requests;

use App\Models\BusinessSetting;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SaveSalesInvoiceRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        if (! BusinessSetting::current()->enable_commission_agent) $this->merge(['commission_agent_id' => null]);
    }

    public function rules(): array
    {
        return [
            'sales_order_id' => ['nullable', Rule::exists('sales_orders','id')->where('status','open')],
            'customer_id' => ['required', Rule::exists('contacts', 'id')->where(fn ($q) => $q->whereIn('type', ['customer', 'both'])->where('status', 'active'))],
            'location_id' => ['nullable', Rule::exists('locations','id')->where('is_active',true)],
            'commission_agent_id' => ['nullable', Rule::exists('contacts', 'id')->where(fn ($q) => $q->where('type', 'commission')->where('status', 'active'))],
            'invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'payment_term' => ['nullable', 'string', 'max:100'], 'journal' => ['required', 'string', 'max:100'],
            'salesperson_id' => ['nullable', Rule::exists('users','id')->where('status','active')], 'recipient_bank_account_id' => ['nullable', Rule::exists('payment_accounts','id')->where('is_active',true)],
            'delivery_date' => ['nullable', 'date'], 'payment_reference' => ['nullable', 'string', 'max:255'],
            'incoterm' => ['nullable', 'string', 'max:100'], 'incoterm_location' => ['nullable', 'string', 'max:255'],
            'fiscal_position' => ['nullable', 'string', 'max:255'], 'payment_method' => ['nullable', 'string', 'max:100'],
            'auto_post' => ['nullable', 'boolean'],
            'tax_display' => ['required', Rule::in(['exclusive', 'inclusive'])],
            'terms' => ['nullable', 'string', 'max:10000'], 'customer_reference' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.line_type' => ['required', Rule::in(['product', 'section', 'note'])],
            'lines.*.product_id' => ['nullable', 'required_if:lines.*.line_type,product', Rule::exists('products','id')->where(fn($q)=>$q->where('is_active',true)->where('not_for_selling',false))],
            'lines.*.product_lot_id' => ['nullable', 'integer', 'exists:product_lots,id'],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.quantity' => ['nullable', 'required_if:lines.*.line_type,product', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['nullable', 'required_if:lines.*.line_type,product', 'numeric', 'min:0', 'max:9999999999999'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates','id')->where(fn($q)=>$q->where('is_tax_group',true)->orWhere('for_tax_group',false))],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $requestedByLot = [];
            foreach ((array) $this->input('lines', []) as $index => $line) {
                $lotId = $line['product_lot_id'] ?? null;
                if (! $lotId) continue;
                $lot = DB::table('product_lots')->join('product_stock_items', 'product_stock_items.id', '=', 'product_lots.product_stock_item_id')
                    ->where('product_lots.id', $lotId)->select('product_stock_items.product_id')->first();
                if (! $lot || (int) $lot->product_id !== (int) ($line['product_id'] ?? 0)) {
                    $validator->errors()->add("lines.$index.product_lot_id", 'Select a lot belonging to this product.');
                    continue;
                }
                $requestedByLot[$lotId] = ($requestedByLot[$lotId] ?? 0) + (float) ($line['quantity'] ?? 0);
            }
            foreach ($requestedByLot as $lotId => $quantity) {
                $available = DB::table('inventory_movements')->where('product_lot_id', $lotId)
                    ->when($this->input('location_id'), fn ($query, $locationId) => $query->where('location_id', $locationId))
                    ->sum('quantity_delta');
                if ($quantity > (float) $available) $validator->errors()->add('lines', 'The selected lot does not have enough available stock.');
            }
        }];
    }
}
