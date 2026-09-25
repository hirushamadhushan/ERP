<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('expiry_period')->nullable()->after('weight');
            $table->string('expiry_period_type', 10)->nullable()->after('expiry_period');
            $table->foreignId('purchase_unit_id')->nullable()->after('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('secondary_unit_id')->nullable()->after('purchase_unit_id')->constrained('units')->restrictOnDelete();
        });

        Schema::create('product_location_details', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('rack', 100)->nullable();
            $table->string('row', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->primary(['product_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_location_details');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secondary_unit_id');
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropColumn(['expiry_period', 'expiry_period_type']);
        });
    }
};
