<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_transfers', function (Blueprint $table) {
            $table->index(['direction', 'warehouse_id', 'created_at'], 'delivery_transfers_direction_warehouse_created_index');
        });
        Schema::table('delivery_consignments', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'delivery_consignments_customer_created_index');
        });
        Schema::table('delivery_returns', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'delivery_returns_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_returns', fn (Blueprint $table) => $table->dropIndex('delivery_returns_status_created_index'));
        Schema::table('delivery_consignments', fn (Blueprint $table) => $table->dropIndex('delivery_consignments_customer_created_index'));
        Schema::table('delivery_transfers', fn (Blueprint $table) => $table->dropIndex('delivery_transfers_direction_warehouse_created_index'));
    }
};
