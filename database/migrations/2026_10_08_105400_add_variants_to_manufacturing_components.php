<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bill_of_material_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
            $table->index(['product_id','product_variant_id'], 'bom_item_product_variant_idx');
        });
        Schema::table('manufacturing_order_components', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
            $table->index(['product_id','product_variant_id'], 'mfg_component_product_variant_idx');
        });
    }
    public function down(): void
    {
        Schema::table('manufacturing_order_components', function (Blueprint $table) {$table->dropIndex('mfg_component_product_variant_idx');$table->dropForeign(['product_variant_id']);$table->dropColumn('product_variant_id');});
        Schema::table('bill_of_material_items', function (Blueprint $table) {$table->dropIndex('bom_item_product_variant_idx');$table->dropForeign(['product_variant_id']);$table->dropColumn('product_variant_id');});
    }
};
