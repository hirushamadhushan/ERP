<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_vehicle_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('delivery_drivers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('delivery_vehicles')->restrictOnDelete();
            $table->string('action', 16);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['driver_id', 'created_at']);
            $table->index(['vehicle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_vehicle_assignment_history');
    }
};
