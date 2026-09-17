<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_template_id')->constrained()->restrictOnDelete();
            $table->string('value', 100);
            $table->string('sku', 100)->unique();
            $table->decimal('purchase_price', 18, 4);
            $table->decimal('purchase_price_inc', 18, 4);
            $table->decimal('margin', 12, 4);
            $table->decimal('selling_price', 18, 4);
            $table->longText('image_path')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'variation_template_id', 'value']);
        });
        Schema::create('combo_product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('item_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
            $table->unique(['combo_product_id', 'item_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_product_items');
        Schema::dropIfExists('product_variants');
    }
};
