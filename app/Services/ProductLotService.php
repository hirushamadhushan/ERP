<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductStockItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductLotService
{
    public function receive(Product $product, array $data, int $userId): ProductLot
    {
        return DB::transaction(function () use ($product, $data, $userId) {
            $product = Product::with('unit')->lockForUpdate()->findOrFail($product->id);
            if (! $product->track_lots || ! $product->manage_stock || $product->enable_serial || $product->product_type === 'combo') {
                throw ValidationException::withMessages(['product' => 'Lot receiving is not enabled for this product.']);
            }

            $variantId = $data['variant_id'] ?? null;
            if ($product->product_type !== 'variable') $variantId = null;
            $stockItemQuery = ProductStockItem::query()->where('product_id', $product->id);
            $stockItem = $variantId
                ? $stockItemQuery->whereHas('variant', fn ($query) => $query->whereKey($variantId))->first()
                : $stockItemQuery->whereDoesntHave('variant')->first();
            if (! $stockItem) {
                $stockItem = ProductStockItem::create(['product_id' => $product->id]);
                if ($variantId) $stockItem->variant()->attach($variantId);
            }
            if (! $product->unit?->allow_decimal && (float) $data['quantity'] !== floor((float) $data['quantity'])) {
                throw ValidationException::withMessages(['quantity' => 'This unit requires whole-number quantities.']);
            }
            if (! empty($data['manufactured_at']) && ! empty($data['expires_at']) && $data['expires_at'] <= $data['manufactured_at']) {
                throw ValidationException::withMessages(['expires_at' => 'Expiry date must be after the manufacturing date.']);
            }

            $lotQuery = ProductLot::query()->where('product_stock_item_id', $stockItem->id)
                ->where('supplier_lot_code', $data['supplier_lot_code'] ?? null)
                ->where('manufactured_at', $data['manufactured_at'] ?? null)
                ->where('expires_at', $data['expires_at'] ?? null)
                ->where('unit_cost', $data['unit_cost'])
                ->where('selling_price', $data['selling_price'] ?? null);
            $lot = ! empty($data['supplier_lot_code']) ? $lotQuery->lockForUpdate()->first() : null;

            if (! $lot) {
                $lot = ProductLot::create([
                    'product_stock_item_id' => $stockItem->id,
                    // A temporary unique value lets the database allocate the primary key.
                    // The final human-readable lot code is derived from that monotonic ID,
                    // making allocation concurrency-safe without a separate counter table.
                    'lot_number' => 'PENDING-'.Str::ulid(),
                    'supplier_lot_code' => $data['supplier_lot_code'] ?? null,
                    'manufactured_at' => $data['manufactured_at'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                    'unit_cost' => $data['unit_cost'],
                    'selling_price' => $data['selling_price'] ?? null,
                    'created_by' => $userId,
                ]);
                $lot->update(['lot_number' => $this->lotNumber($lot)]);
            }

            $transaction = InventoryTransaction::create([
                'transaction_type' => 'receipt',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'occurred_at' => now(),
            ]);
            InventoryMovement::create([
                'inventory_transaction_id' => $transaction->id,
                'product_lot_id' => $lot->id,
                'location_id' => $data['location_id'],
                'quantity_delta' => $data['quantity'],
                'created_at' => now(),
            ]);

            return $lot;
        });
    }

    private function lotNumber(ProductLot $lot): string
    {
        return sprintf('LOT-%s-%06d', $lot->created_at->format('Ymd'), $lot->id);
    }

    public function stockByProductAndLocation(iterable $productIds): array
    {
        return InventoryMovement::query()
            ->join('product_lots', 'product_lots.id', '=', 'inventory_movements.product_lot_id')
            ->join('product_stock_items', 'product_stock_items.id', '=', 'product_lots.product_stock_item_id')
            ->leftJoin('product_stock_item_variants', 'product_stock_item_variants.product_stock_item_id', '=', 'product_stock_items.id')
            ->whereIn('product_stock_items.product_id', $productIds)
            ->selectRaw('product_stock_items.product_id, product_stock_item_variants.product_variant_id, inventory_movements.location_id, SUM(inventory_movements.quantity_delta) as quantity, SUM(inventory_movements.quantity_delta * product_lots.unit_cost) as inventory_value, SUM(inventory_movements.quantity_delta * product_lots.selling_price) as sale_value')
            ->groupBy('product_stock_items.product_id', 'product_stock_item_variants.product_variant_id', 'inventory_movements.location_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id.':'.($row->product_variant_id ?: 'product').':'.$row->location_id)
            ->all();
    }
}
