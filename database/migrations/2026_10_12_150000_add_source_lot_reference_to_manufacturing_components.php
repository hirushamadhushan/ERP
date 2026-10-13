<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manufacturing_order_components', fn (Blueprint $table) => $table->string('source_lot_reference', 100)->nullable()->after('product_lot_id'));
    }

    public function down(): void
    {
        Schema::table('manufacturing_order_components', fn (Blueprint $table) => $table->dropColumn('source_lot_reference'));
    }
};
