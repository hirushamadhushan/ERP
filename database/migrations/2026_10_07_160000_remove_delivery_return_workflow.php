<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('delivery_return_lines');
        Schema::dropIfExists('delivery_returns');

        Schema::table('delivery_consignment_lines', function (Blueprint $table) {
            $table->dropColumn('return_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_consignment_lines', function (Blueprint $table) {
            $table->decimal('return_quantity', 18, 4)->default(0);
        });

        Schema::create('delivery_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->unique()->constrained('delivery_consignments')->restrictOnDelete();
            $table->string('number', 32)->unique();
            $table->string('status', 24)->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('unloading_transfer_id')->nullable()->unique()->constrained('delivery_transfers')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'delivery_returns_status_created_index');
        });
        Schema::create('delivery_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_return_id')->constrained('delivery_returns')->cascadeOnDelete();
            $table->foreignId('consignment_line_id')->unique()->constrained('delivery_consignment_lines')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
        });
    }
};
