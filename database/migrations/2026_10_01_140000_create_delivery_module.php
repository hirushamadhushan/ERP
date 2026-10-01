<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('name', 150);
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->string('chassis_number', 100)->nullable();
            $table->string('engine_number', 100)->nullable();
            $table->date('insurance_expires_at')->nullable();
            $table->date('revenue_license_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('identity_number', 50)->nullable();
            $table->string('license_number', 80)->nullable()->unique();
            $table->date('license_expires_at')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('delivery_vehicle_stores', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->primary()->constrained('delivery_vehicles')->restrictOnDelete();
            $table->foreignId('location_id')->unique()->constrained()->restrictOnDelete();
        });
        Schema::create('delivery_vehicle_assignments', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->primary()->constrained('delivery_vehicles')->restrictOnDelete();
            $table->foreignId('driver_id')->unique()->constrained('delivery_drivers')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
        });
        Schema::create('delivery_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('payload_hash', 64);
            $table->foreignId('vehicle_id')->constrained('delivery_vehicles')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('delivery_drivers')->restrictOnDelete();
            $table->foreignId('inventory_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->string('direction', 10);
            $table->timestamps();
            $table->index(['vehicle_id', 'created_at']);
        });
        Schema::create('delivery_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('delivery_transfers')->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained('product_stock_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
        });
        Schema::create('delivery_transfer_line_lots', function (Blueprint $table) {
            $table->foreignId('line_id')->primary()->constrained('delivery_transfer_lines')->restrictOnDelete();
            $table->foreignId('lot_id')->constrained('product_lots')->restrictOnDelete();
        });
        Schema::create('delivery_transfer_line_serials', function (Blueprint $table) {
            $table->foreignId('line_id')->constrained('delivery_transfer_lines')->restrictOnDelete();
            $table->foreignId('serial_id')->constrained('product_serial_numbers')->restrictOnDelete();
            $table->primary(['line_id', 'serial_id']);
        });
    }

    public function down(): void
    {
        foreach (['delivery_transfer_line_serials', 'delivery_transfer_line_lots', 'delivery_transfer_lines', 'delivery_transfers', 'delivery_vehicle_assignments', 'delivery_vehicle_stores', 'delivery_drivers', 'delivery_vehicles'] as $table) Schema::dropIfExists($table);
    }
};
