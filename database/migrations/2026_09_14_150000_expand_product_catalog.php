<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku_key', 100)->nullable()->unique();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('barcode_type', 20)->default('CODE128');
            $table->boolean('manage_stock')->default(true);
            $table->boolean('enable_serial')->default(false);
            $table->boolean('not_for_selling')->default(false);
            $table->decimal('alert_quantity', 18, 4)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('brochure_path')->nullable();
            $table->string('brochure_name')->nullable();
            $table->string('variant_image_path')->nullable();
            $table->string('weight', 100)->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('product_type', 20)->default('single');
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->string('selling_price_tax_type', 20)->default('exclusive');
            $table->decimal('purchase_price', 18, 4)->default(0);
            $table->decimal('purchase_price_inc', 18, 4)->default(0);
            $table->decimal('margin', 12, 4)->default(25);
            $table->decimal('selling_price', 18, 4)->default(0);
            $table->decimal('our_price', 18, 4)->nullable();
        });
        Schema::create('location_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_quantity', 18, 4)->default(0);
            $table->primary(['product_id', 'location_id']);
        });
        // Preserve the availability of pre-catalog serial products and their existing IDs.
        foreach (DB::table('products')->orderBy('id')->cursor() as $product) {
            DB::table('products')->where('id', $product->id)->update(['sku_key' => strtolower($product->code), 'enable_serial' => true]);
            foreach (DB::table('locations')->pluck('id') as $location) {
                DB::table('location_product')->insert(['product_id' => $product->id, 'location_id' => $location, 'opening_quantity' => 0]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('location_product');
        Schema::table('products', function (Blueprint $table) {
            foreach (['unit_id', 'brand_id', 'category_id', 'subcategory_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['sku_key', 'barcode_type', 'manage_stock', 'enable_serial', 'not_for_selling', 'alert_quantity', 'description', 'image_path', 'brochure_path', 'brochure_name', 'variant_image_path', 'weight', 'custom_fields', 'product_type', 'tax_rate', 'selling_price_tax_type', 'purchase_price', 'purchase_price_inc', 'margin', 'selling_price', 'our_price']);
        });
        Schema::table('categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
    }
};
