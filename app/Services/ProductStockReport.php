<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class ProductStockReport
{
    /**
     * Build one report row per product location. Serial-tracked stock is based
     * on available serials; other products use their opening-stock balance.
     */
    public function build(Collection $products, Collection $serialStock): array
    {
        $rows = $products->flatMap(function ($product) use ($serialStock) {
            return $product->locations->map(function ($location) use ($product, $serialStock) {
                $stock = $product->enable_serial
                    ? (float) ($serialStock->get($product->id.':'.$location->id)?->quantity ?? 0)
                    : (float) $location->pivot->opening_quantity;
                $purchaseValue = $stock * (float) $product->purchase_price;
                $saleValue = $stock * (float) $product->selling_price;

                return [
                    'product' => $product,
                    'location' => $location,
                    'variation' => 'Default',
                    'stock' => $stock,
                    'purchase_value' => $purchaseValue,
                    'sale_value' => $saleValue,
                    'potential_profit' => $saleValue - $purchaseValue,
                    // These become transaction aggregates when their modules are added.
                    'sold' => 0.0,
                    'transferred' => 0.0,
                    'adjusted' => 0.0,
                ];
            });
        })->values();

        return [
            'rows' => $rows,
            'totals' => [
                'stock' => $rows->sum('stock'),
                'purchase_value' => $rows->sum('purchase_value'),
                'sale_value' => $rows->sum('sale_value'),
                'potential_profit' => $rows->sum('potential_profit'),
                'sold' => $rows->sum('sold'),
                'transferred' => $rows->sum('transferred'),
                'adjusted' => $rows->sum('adjusted'),
            ],
        ];
    }
}
