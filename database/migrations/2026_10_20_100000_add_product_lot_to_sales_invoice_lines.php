<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoice_lines', function (Blueprint $table) {
            $table->foreignId('product_lot_id')->nullable()->after('product_variant_id')->constrained('product_lots')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_lot_id'));
    }
};
