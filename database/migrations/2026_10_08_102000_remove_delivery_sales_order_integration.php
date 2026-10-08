<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('delivery_consignment_line_allocations');
        if (Schema::hasColumn('delivery_consignments', 'sales_order_id')) {
            Schema::table('delivery_consignments', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_order_id'));
        }
        if (Schema::hasColumn('delivery_consignments', 'sales_order_reference')) {
            Schema::table('delivery_consignments', fn (Blueprint $table) => $table->dropColumn('sales_order_reference'));
        }
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
    }

    public function down(): void {}
};
