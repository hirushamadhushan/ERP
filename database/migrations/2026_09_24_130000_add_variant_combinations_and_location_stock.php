<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_template_value_id')->constrained()->restrictOnDelete();
            $table->unique(['product_variant_id', 'variation_template_value_id'], 'product_variant_value_unique');
        });

        Schema::create('product_variant_location_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_quantity', 18, 4)->default(0);
            $table->unique(['product_variant_id', 'location_id'], 'variant_location_stock_unique');
        });

        // Preserve every existing single-template variant as a one-value
        // combination before the application starts using the pivot table.
        DB::table('product_variants')
            ->whereNotNull('variation_template_value_id')
            ->orderBy('id')
            ->each(function ($variant) {
                DB::table('product_variant_values')->insertOrIgnore([
                    'product_variant_id' => $variant->id,
                    'variation_template_value_id' => $variant->variation_template_value_id,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_location_stocks');
        Schema::dropIfExists('product_variant_values');
    }
};
