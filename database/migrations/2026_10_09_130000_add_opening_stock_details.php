<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['location_product', 'product_variant_location_stocks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('opening_unit_cost', 18, 4)->nullable();
                $table->date('opening_expiry_date')->nullable();
                $table->dateTime('opening_stocked_at')->nullable();
                $table->text('opening_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['location_product', 'product_variant_location_stocks'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn([
                'opening_unit_cost', 'opening_expiry_date', 'opening_stocked_at', 'opening_note',
            ]));
        }
    }
};
