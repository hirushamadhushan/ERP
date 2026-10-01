<?php
namespace App\Services;

use App\Models\{InventoryMovement, Product, ProductSerialNumber};
use Illuminate\Support\Facades\DB;

class DeliveryInventory
{
    public function products(int $locationId, string $search = '')
    {
        return Product::where('manage_stock', true)->where('product_type', '!=', 'combo')
            ->whereHas('locations', fn ($q) => $q->where('locations.id', $locationId))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
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

    public function options(int $locationId, string $search): array
    {
        $rows = [];
        foreach ($this->products($locationId, $search)->limit(25)->get() as $product) {
            $variants = $product->product_type === 'variable' ? $product->variants : collect([null]);
            foreach ($variants as $variant) {
                $base = ['product_id' => $product->id, 'variant_id' => $variant?->id, 'unit' => $product->unit?->short_name, 'decimal' => (bool) $product->unit?->allow_decimal];
                $label = $product->name.' / '.$product->code.($variant ? ' / '.$variant->value : '');
                if ($product->track_lots) {
                    $lots = $product->lots()->whereHas('stockItem', fn ($q) => $variant ? $q->whereHas('variant', fn ($v) => $v->whereKey($variant->id)) : $q->whereDoesntHave('variant'))
                        ->withSum(['movements as available' => fn ($q) => $q->where('location_id', $locationId)], 'quantity_delta')
                        ->whereHas('movements', fn ($q) => $q->where('location_id', $locationId))->orderBy('expires_at')->limit(100)->get();
                    foreach ($lots as $lot) if ($lot->available > 0) $rows[] = $base + ['label' => $label.' / '.$lot->lot_number.' / expiry '.($lot->expires_at?->format('Y-m-d') ?? 'none'), 'available' => $lot->available, 'lot_id' => $lot->id];
                } elseif ($product->enable_serial) {
                    foreach (ProductSerialNumber::forProduct($product->id)->where('product_variant_id', $variant?->id)->where('location_id', $locationId)->where('status', 'available')->orderBy('id')->limit(100)->get() as $serial) {
                        $rows[] = $base + ['label' => $label.' / Serial '.$serial->serial_number, 'available' => 1, 'serial_id' => $serial->id];
                    }
                } else {
                    $quantity = $variant ? ($variant->locationStocks->firstWhere('location_id', $locationId)?->opening_quantity ?? 0) : $product->locations->first()->pivot->opening_quantity;
                    if ($quantity > 0) $rows[] = $base + ['label' => $label, 'available' => $quantity];
                }
            }
        }
        return $rows;
    }
}
