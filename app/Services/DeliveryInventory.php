<?php
namespace App\Services;

use App\Models\{InventoryMovement, Product, ProductSerialNumber};
use Illuminate\Support\Facades\DB;

class DeliveryInventory
{
    public function products(int $locationId, string $search = '')
    {
        $like = '%'.$search.'%';

        return Product::where('manage_stock', true)->where('product_type', '!=', 'combo')
            ->whereHas('locations', fn ($q) => $q->where('locations.id', $locationId))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhereHas('variants', fn ($variants) => $variants->where(fn ($variant) => $variant
                        ->where('sku', 'like', $like)
                        ->orWhereHas('variationValue', fn ($value) => $value->where('value', 'like', $like))
                        ->orWhereHas('variationValues', fn ($value) => $value->where('value', 'like', $like))))
                    ->orWhereHas('lots', fn ($lots) => $lots->where(fn ($lot) => $lot
                        ->where('lot_number', 'like', $like)
                        ->orWhere('supplier_lot_code', 'like', $like)))
                    ->orWhereExists(fn ($serials) => $serials->selectRaw('1')
                        ->from('product_serial_numbers as search_serials')
                        ->leftJoin('product_variants as search_variants', 'search_variants.id', '=', 'search_serials.product_variant_id')
                        ->whereRaw('COALESCE(search_serials.product_id, search_variants.product_id) = products.id')
                        ->where('search_serials.serial_number', 'like', $like));
            }))
            ->with(['unit', 'variants.locationStocks', 'variants.variationValues', 'variants.variationValue', 'locations' => fn ($q) => $q->where('locations.id', $locationId)])
            ->orderBy('name');
    }

    public function balances($products, int $locationId): array
    {
        $serials = ProductSerialNumber::query()->leftJoin('product_variants as sv', 'sv.id', '=', 'product_serial_numbers.product_variant_id')
            ->where('location_id', $locationId)->where('status', 'available')
            ->selectRaw('COALESCE(product_serial_numbers.product_id, sv.product_id) as product_id, product_variant_id, location_id, COUNT(*) as quantity')
            ->groupByRaw('COALESCE(product_serial_numbers.product_id, sv.product_id), product_variant_id, location_id')->get()
            ->keyBy(fn ($r) => $r->product_id.':'.($r->product_variant_id ?: 'product').':'.$r->location_id);
        return app(ProductStockReport::class)->build($products, $serials, app(ProductLotService::class)->stockByProductAndLocation($products->pluck('id')))['rows']->filter(fn ($r) => $r['stock'] > 0)->values()->all();
    }

    public function options(int $locationId, string $search, bool $loading = false): array
    {
        $rows = [];
        $matches = static function (mixed ...$values) use ($search): bool {
            if ($search === '') return true;
            foreach ($values as $value) if ($value !== null && mb_stripos((string) $value, $search) !== false) return true;
            return false;
        };

        foreach ($this->products($locationId, $search)->when($loading, fn ($query) => $query->where('is_active', true))->limit(25)->get() as $product) {
            $productMatches = $matches($product->name, $product->code);
            $variants = $product->product_type === 'variable' ? $product->variants : collect([null]);
            foreach ($variants as $variant) {
                $base = ['product_id' => $product->id, 'variant_id' => $variant?->id, 'product_name' => $product->name, 'sku' => $variant?->sku ?: $product->code,
                    'scan_codes' => array_values(array_filter([$product->code, $variant?->sku])), 'unit' => $product->unit?->short_name, 'decimal' => (bool) $product->unit?->allow_decimal];
                $label = $product->name.' / '.$product->code.($variant ? ' / '.$variant->value : '');
                $variantMatches = $productMatches || $matches($variant?->value, $variant?->sku);
                if ($product->enable_serial) {
                    foreach (ProductSerialNumber::forProduct($product->id)->where('product_variant_id', $variant?->id)->where('location_id', $locationId)->where('status', 'available')->with('productLot')->orderBy('id')->limit(100)->get() as $serial) {
                        if ($variantMatches || $matches($serial->serial_number, $serial->productLot?->lot_number)) $rows[] = array_merge($base, ['label' => $label.' / Serial '.$serial->serial_number.($serial->productLot ? ' / '.$serial->productLot->lot_number : ''), 'available' => 1, 'serial_id' => $serial->id, 'serial_number' => $serial->serial_number, 'lot_id' => $serial->product_lot_id, 'lot_number' => $serial->productLot?->lot_number, 'scan_codes' => array_values(array_filter([$product->code, $variant?->sku, $serial->serial_number, $serial->productLot?->lot_number]))]);
                    }
                } elseif ($product->track_lots) {
                    $lots = $product->lots()->whereHas('stockItem', fn ($q) => $variant ? $q->whereHas('variant', fn ($v) => $v->whereKey($variant->id)) : $q->whereDoesntHave('variant'))
                        ->withSum(['movements as available' => fn ($q) => $q->where('location_id', $locationId)], 'quantity_delta')
                        ->whereHas('movements', fn ($q) => $q->where('location_id', $locationId))
                        ->when($loading, fn ($query) => $query->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today())))
                        ->orderBy('expires_at')->limit(100)->get();
                    foreach ($lots as $lot) if ($lot->available > 0 && ($variantMatches || $matches($lot->lot_number, $lot->supplier_lot_code))) $rows[] = array_merge($base, ['label' => $label.' / '.$lot->lot_number.' / expiry '.($lot->expires_at?->format('Y-m-d') ?? 'none'), 'available' => $lot->available, 'lot_id' => $lot->id, 'lot_number' => $lot->lot_number, 'scan_codes' => array_values(array_filter([$product->code, $variant?->sku, $lot->lot_number, $lot->supplier_lot_code]))]);
                } else {
                    if (! $variantMatches) continue;
                    $quantity = $variant ? ($variant->locationStocks->firstWhere('location_id', $locationId)?->opening_quantity ?? 0) : $product->locations->first()->pivot->opening_quantity;
                    if ($quantity > 0) $rows[] = $base + ['label' => $label, 'available' => $quantity];
                }
            }
        }
        return $rows;
    }
}
