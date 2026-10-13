<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $products = DB::table('products')->where('is_raw_material', true)->where('manage_stock', true)->where('enable_serial', false)->whereIn('product_type', ['single','variable'])->orderBy('id')->get();
            foreach ($products as $product) {
                DB::table('products')->where('id', $product->id)->update(['track_lots' => true, 'updated_at' => now()]);
                if ($product->product_type === 'variable') {
                    foreach (DB::table('product_variants')->where('product_id', $product->id)->get() as $variant) {
                        $itemId = DB::table('product_stock_item_variants')->where('product_variant_id', $variant->id)->value('product_stock_item_id');
                        if (! $itemId) {
                            $itemId = DB::table('product_stock_items')->insertGetId(['product_id'=>$product->id,'created_at'=>now(),'updated_at'=>now()]);
                            DB::table('product_stock_item_variants')->insert(['product_stock_item_id'=>$itemId,'product_variant_id'=>$variant->id,'created_at'=>now(),'updated_at'=>now()]);
                        }
                        foreach (DB::table('product_variant_location_stocks')->where('product_variant_id', $variant->id)->where('opening_quantity', '>', 0)->get() as $stock) {
                            $this->receiveOpeningLot($itemId, $product, $variant, $stock->location_id, $stock->opening_quantity, $stock->opening_unit_cost ?? $variant->purchase_price, $stock->opening_stocked_at ?? now(), $stock->opening_note ?? null);
                            DB::table('product_variant_location_stocks')->where('id', $stock->id)->update(['opening_quantity'=>0]);
                        }
                    }
                } else {
                    $itemId = DB::table('product_stock_items')->where('product_id', $product->id)->whereNotExists(fn ($query) => $query->selectRaw('1')->from('product_stock_item_variants')->whereColumn('product_stock_item_variants.product_stock_item_id','product_stock_items.id'))->value('id');
                    if (! $itemId) $itemId = DB::table('product_stock_items')->insertGetId(['product_id'=>$product->id,'created_at'=>now(),'updated_at'=>now()]);
                    foreach (DB::table('location_product')->where('product_id', $product->id)->where('opening_quantity', '>', 0)->get() as $stock) {
                        $this->receiveOpeningLot($itemId, $product, null, $stock->location_id, $stock->opening_quantity, $stock->opening_unit_cost ?? $product->purchase_price, $stock->opening_stocked_at ?? now(), $stock->opening_note ?? null);
                        DB::table('location_product')->where('product_id',$product->id)->where('location_id',$stock->location_id)->update(['opening_quantity'=>0]);
                    }
                }
            }
        });
    }

    private function receiveOpeningLot(int $itemId, object $product, ?object $variant, int $locationId, mixed $quantity, mixed $unitCost, mixed $occurredAt, ?string $note): void
    {
        $stamp = now();
        $lotId = DB::table('product_lots')->insertGetId(['product_stock_item_id'=>$itemId,'lot_number'=>'BACKFILL-'.uniqid(),'supplier_lot_code'=>null,'manufactured_at'=>null,'expires_at'=>null,'unit_cost'=>$unitCost ?: ($variant->purchase_price ?? $product->purchase_price),'selling_price'=>$variant->selling_price ?? $product->selling_price,'created_by'=>null,'created_at'=>$stamp,'updated_at'=>$stamp]);
        DB::table('product_lots')->where('id',$lotId)->update(['lot_number'=>sprintf('LOT-%s-%06d',$stamp->format('Ymd'),$lotId)]);
        $transactionId = DB::table('inventory_transactions')->insertGetId(['transaction_type'=>'opening_stock','reference'=>'OPENING-'.$product->code,'notes'=>$note ?: 'Opening stock converted to a traceable lot.','created_by'=>null,'occurred_at'=>$occurredAt ?: $stamp,'created_at'=>$stamp,'updated_at'=>$stamp]);
        DB::table('inventory_movements')->insert(['inventory_transaction_id'=>$transactionId,'product_lot_id'=>$lotId,'location_id'=>$locationId,'quantity_delta'=>$quantity,'created_at'=>$stamp]);
    }

    public function down(): void {}
};
