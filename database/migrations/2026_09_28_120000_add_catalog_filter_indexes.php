<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index the columns used by the product and serial-number list filters.
     * These indexes keep page navigation responsive as catalog data grows.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'product_type'], 'products_active_type_index');
            $table->index('tax_rate', 'products_tax_rate_index');
        });

        Schema::table('location_product', function (Blueprint $table) {
            // The primary key starts with product_id. Location filtering needs
            // the reverse order to find all matching products efficiently.
            $table->index(['location_id', 'product_id'], 'location_product_location_product_index');
        });

        Schema::table('product_serial_numbers', function (Blueprint $table) {
            $table->index(['status', 'product_id', 'location_id'], 'serial_numbers_filter_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_serial_numbers', fn (Blueprint $table) => $table->dropIndex('serial_numbers_filter_index'));
        Schema::table('location_product', fn (Blueprint $table) => $table->dropIndex('location_product_location_product_index'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_type_index');
            $table->dropIndex('products_tax_rate_index');
        });
    }
};
