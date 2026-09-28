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
            if ($product->product_type === 'variable') {
                return $product->variants->flatMap(function ($variant) use ($product, $serialStock) {
                    return $product->locations->map(function ($location) use ($product, $variant, $serialStock) {
                        $stock = $product->enable_serial
                            ? (float) ($serialStock->get($product->id.':'.$variant->id.':'.$location->id)?->quantity ?? 0)
                            : (float) ($variant->locationStocks->firstWhere('location_id', $location->id)?->opening_quantity ?? 0);

                        return $this->row($product, $location, $variant->value ?: 'Default', $stock, (float) $variant->purchase_price, (float) $variant->selling_price);
                    });
                });
            }

            return $product->locations->map(function ($location) use ($product, $serialStock) {
                $stock = $product->enable_serial
                    ? (float) ($serialStock->get($product->id.':product:'.$location->id)?->quantity ?? 0)
                    : (float) $location->pivot->opening_quantity;
                return $this->row($product, $location, 'Default', $stock, (float) $product->purchase_price, (float) $product->selling_price);
            });
        })->values();

        $stockByProduct = $rows
            ->groupBy(fn (array $row) => $row['product']->id)
            ->map(fn (Collection $productRows) => $productRows->sum('stock'));

        return [
            'rows' => $rows,
            // The catalogue's Current Stock must use the same rows as this report.
            // This includes every variation and every assigned location.
            'stock_by_product' => $stockByProduct,
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

    private function row($product, $location, string $variation, float $stock, float $purchasePrice, float $sellingPrice): array
    {
        $purchaseValue = $stock * $purchasePrice;
        $saleValue = $stock * $sellingPrice;

        return compact('product', 'location', 'variation', 'stock', 'purchaseValue', 'saleValue') + [
            'purchase_value' => $purchaseValue,
            'sale_value' => $saleValue,
            'potential_profit' => $saleValue - $purchaseValue,
            'sold' => 0.0,
            'transferred' => 0.0,
            'adjusted' => 0.0,
        ];
    }
}
