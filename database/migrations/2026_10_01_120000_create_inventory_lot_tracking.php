<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_lots')->default(false)->after('enable_serial')->index();
        });

        Schema::create('product_stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index('product_id');
        });
        Schema::create('product_stock_item_variants', function (Blueprint $table) {
            $table->foreignId('product_stock_item_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        foreach (DB::table('products')->orderBy('id')->get(['id', 'product_type']) as $product) {
            $variants = $product->product_type === 'variable'
                ? DB::table('product_variants')->where('product_id', $product->id)->orderBy('id')->pluck('id')
                : collect([null]);
            foreach ($variants as $variantId) {
                $stockItemId = DB::table('product_stock_items')->insertGetId([
                    'product_id' => $product->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($variantId !== null) {
                    DB::table('product_stock_item_variants')->insert([
                        'product_stock_item_id' => $stockItemId,
                        'product_variant_id' => $variantId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::create('product_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_stock_item_id')->constrained()->restrictOnDelete();
            $table->string('lot_number', 40)->unique();
            $table->string('supplier_lot_code', 100)->nullable();
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('selling_price', 18, 4);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_stock_item_id', 'supplier_lot_code'], 'product_lot_lookup');
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 30);
            $table->string('reference', 100)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['transaction_type', 'occurred_at']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_delta', 18, 4);
            $table->timestamp('created_at')->index();
            $table->index(['product_lot_id', 'location_id', 'id'], 'lot_location_ledger');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('product_lots');
        Schema::dropIfExists('product_stock_item_variants');
        Schema::dropIfExists('product_stock_items');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['track_lots']);
            $table->dropColumn('track_lots');
        });
    }
};
