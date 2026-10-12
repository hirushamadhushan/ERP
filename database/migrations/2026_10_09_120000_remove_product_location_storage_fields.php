<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_location_details');

        Schema::table('business_product_settings', function (Blueprint $table) {
            $table->dropColumn(['enable_racks', 'enable_row', 'enable_position']);
        });
    }

    public function down(): void
    {
        Schema::table('business_product_settings', function (Blueprint $table) {
            $table->boolean('enable_racks')->default(false);
            $table->boolean('enable_row')->default(false);
            $table->boolean('enable_position')->default(false);
        });

        Schema::create('product_location_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('rack', 100)->nullable();
            $table->string('row', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'location_id']);
        });
    }
};
