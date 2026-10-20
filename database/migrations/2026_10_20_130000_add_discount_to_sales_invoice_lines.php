<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoice_lines', function (Blueprint $table) {
            $table->decimal('discount_percent', 7, 4)->default(0);
            $table->decimal('discount_amount', 20, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_lines', fn (Blueprint $table) => $table->dropColumn(['discount_percent', 'discount_amount']));
    }
};
