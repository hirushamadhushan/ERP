<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('title')->nullable()->after('order_number');
            $table->string('reference')->nullable()->after('status');
            $table->date('valid_until')->nullable()->after('order_date');
            $table->text('payment_terms')->nullable()->after('terms');
            $table->decimal('discount_percentage', 8, 2)->default(0)->after('total_amount');
            $table->decimal('discount_amount', 18, 2)->default(0)->after('discount_percentage');
            $table->decimal('net_total', 18, 2)->default(0)->after('discount_amount');
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->string('item_type', 20)->default('item')->after('sales_order_id');
            $table->decimal('available_qty', 18, 4)->nullable()->after('description');
            $table->decimal('discount_percentage', 8, 2)->default(0)->after('unit_price');
            $table->decimal('discount_amount', 18, 2)->default(0)->after('discount_percentage');
            $table->decimal('line_total', 18, 2)->default(0)->after('discount_amount');
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'available_qty', 'discount_percentage', 'discount_amount', 'line_total']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['title', 'reference', 'valid_until', 'payment_terms', 'discount_percentage', 'discount_amount', 'net_total']);
        });
    }
};
