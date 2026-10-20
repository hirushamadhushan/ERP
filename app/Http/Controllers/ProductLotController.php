<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveProductLotRequest;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLot;
use App\Services\ProductLotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class ProductLotController
 *
 * Manages batch/lot lifecycle, expiration tracking, traceability,
 * and receiving movements for lot-tracked products.
 */
class ProductLotController extends Controller
{
    /**
     * Display lot listing, location balances, and inventory movement history for a product.
     *
     * @param Product $product
     * @return View
     */
    public function index(Product $product): View
    {
        abort_unless($product->track_lots, 404);

        $lots = ProductLot::query()
            ->whereHas('stockItem', fn ($query) => $query->where('product_id', $product->id))
            ->with(['stockItem.variant.variationValues', 'manufacturingOrder'])
            ->withSum('movements as current_quantity', 'quantity_delta')
            ->latest('id')
            ->paginate(25);

        $lotLocationBalances = InventoryMovement::query()
            ->join('locations', 'locations.id', '=', 'inventory_movements.location_id')
            ->whereIn('product_lot_id', $lots->getCollection()->pluck('id'))
            ->selectRaw('product_lot_id, locations.name as location_name, SUM(quantity_delta) as quantity')
            ->groupBy('product_lot_id', 'locations.name')
            ->get()
            ->groupBy('product_lot_id');

        return view('products.lots', [
            'product' => $product->load(['locations', 'variants.variationValues']),
            'lots' => $lots,
            'lotLocationBalances' => $lotLocationBalances,
            'movements' => InventoryMovement::query()
                ->whereHas('lot.stockItem', fn ($query) => $query->where('product_id', $product->id))
                ->with(['lot', 'location', 'transaction'])
                ->latest('id')
                ->limit(50)
                ->get(),
            'locations' => Location::query()
                ->whereIn('id', $product->locations()->pluck('locations.id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Receive new inventory directly into an identified product lot.
     *
     * @param ReceiveProductLotRequest $request
     * @param Product $product
     * @param ProductLotService $lots
     * @return RedirectResponse
     */
    public function store(ReceiveProductLotRequest $request, Product $product, ProductLotService $lots): RedirectResponse
    {
        try {
            $lot = $lots->receive($product, $request->validated(), (int) $request->user()->id);

            return redirect()->route('products.catalog.index', ['product_id' => $product->id])
                ->with('success', 'Stock received into lot ' . $lot->lot_number . '. The product list now shows its updated stock.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to receive stock into product lot', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'payload' => $request->except(['_token']),
            ]);

            return back()->withInput()->with('error', 'Unable to receive lot stock: ' . $e->getMessage());
        }
    }
}
