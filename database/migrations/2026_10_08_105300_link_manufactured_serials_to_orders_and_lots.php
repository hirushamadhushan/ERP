<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_serial_numbers', function (Blueprint $table) {
            $table->foreignId('manufacturing_order_id')->nullable()->after('location_id')->constrained('manufacturing_orders')->restrictOnDelete();
            $table->foreignId('product_lot_id')->nullable()->after('manufacturing_order_id')->constrained('product_lots')->restrictOnDelete();
            $table->index(['manufacturing_order_id','status'], 'serial_manufacturing_status_idx');
            $table->index(['product_lot_id','status'], 'serial_lot_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_serial_numbers', function (Blueprint $table) {
            $table->dropIndex('serial_manufacturing_status_idx');
            $table->dropIndex('serial_lot_status_idx');
            $table->dropForeign(['manufacturing_order_id']);
            $table->dropForeign(['product_lot_id']);
            $table->dropColumn(['manufacturing_order_id','product_lot_id']);
        });
    }
};
