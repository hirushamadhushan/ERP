<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_invoices', 'stock_type')) {
                $table->string('stock_type', 50)->default('Main Stock')->after('journal');
            }
            if (!Schema::hasColumn('sales_invoices', 'discount_percent')) {
                $table->decimal('discount_percent', 8, 2)->default(0)->after('untaxed_amount');
            }
            if (!Schema::hasColumn('sales_invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 18, 2)->default(0)->after('discount_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['stock_type', 'discount_percent', 'discount_amount']);
        });
    }
};
