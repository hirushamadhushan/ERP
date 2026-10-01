<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveProductLotRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\InventoryMovement;
use App\Services\ProductLotService;

class ProductLotController extends Controller
{
    public function index(Product $product)
    {
        abort_unless($product->track_lots, 404);

        $lots = ProductLot::query()->whereHas('stockItem', fn ($query) => $query->where('product_id', $product->id))
            ->with('stockItem.variant.variationValues')
            ->withSum('movements as current_quantity', 'quantity_delta')
            ->latest('id')->paginate(25);
        $lotLocationBalances = InventoryMovement::query()
            ->join('locations', 'locations.id', '=', 'inventory_movements.location_id')
            ->whereIn('product_lot_id', $lots->getCollection()->pluck('id'))
            ->selectRaw('product_lot_id, locations.name as location_name, SUM(quantity_delta) as quantity')
            ->groupBy('product_lot_id', 'locations.name')
            ->get()->groupBy('product_lot_id');

        return view('products.lots', [
            'product' => $product->load(['locations', 'variants.variationValues']),
            'lots' => $lots,
            'lotLocationBalances' => $lotLocationBalances,
            'movements' => InventoryMovement::query()->whereHas('lot.stockItem', fn ($query) => $query->where('product_id', $product->id))
                ->with(['lot', 'location', 'transaction'])->latest('id')->limit(50)->get(),
            'locations' => Location::query()->whereIn('id', $product->locations()->pluck('locations.id'))->orderBy('name')->get(),
        ]);
    }

    public function store(ReceiveProductLotRequest $request, Product $product, ProductLotService $lots)
    {
        $lot = $lots->receive($product, $request->validated(), (int) $request->user()->id);

        return redirect()->route('products.catalog.index', ['product_id' => $product->id])
            ->with('success', 'Stock received into lot '.$lot->lot_number.'. The product list now shows its updated stock.');
    }
}
