<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_consignments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('loading_transfer_id')->unique()->constrained('delivery_transfers')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('contacts')->restrictOnDelete();
            $table->string('sales_order_reference', 100)->nullable();
            $table->string('status', 24)->default('loaded')->index();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('receiver_name', 150)->nullable();
            $table->string('receiver_phone', 40)->nullable();
            $table->string('delivery_address', 500);
            $table->text('notes')->nullable();
            $table->text('proof_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });
        Schema::create('delivery_consignment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained('delivery_consignments')->cascadeOnDelete();
            $table->foreignId('transfer_line_id')->unique()->constrained('delivery_transfer_lines')->restrictOnDelete();
            $table->decimal('delivered_quantity', 18, 4)->default(0);
            $table->decimal('damaged_quantity', 18, 4)->default(0);
            $table->decimal('missing_quantity', 18, 4)->default(0);
            $table->decimal('return_quantity', 18, 4)->default(0);
        });
        Schema::create('delivery_consignment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained('delivery_consignments')->cascadeOnDelete();
            $table->string('event', 32);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['consignment_id', 'created_at']);
        });
        Schema::create('delivery_consignment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained('delivery_consignments')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['delivery_consignment_proofs', 'delivery_consignment_events', 'delivery_consignment_lines', 'delivery_consignments'] as $table) Schema::dropIfExists($table);
    }
};
