<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number', 50)->unique();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->string('order_number', 50)->nullable();
            $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->date('invoice_date')->nullable();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('salesperson_name', 150)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->foreignId('delivery_vehicle_id')->nullable()->constrained('delivery_vehicles')->nullOnDelete();
            $table->string('vehicle_number', 50)->nullable();
            $table->date('dispatch_date');
            $table->string('status', 30)->default('Pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_dispatch_id')->constrained('sales_dispatches')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_code', 100);
            $table->string('item_name', 255);
            $table->decimal('inv_qty', 15, 4)->default(1);
            $table->decimal('qty', 15, 4)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_dispatch_items');
        Schema::dropIfExists('sales_dispatches');
    }
};
