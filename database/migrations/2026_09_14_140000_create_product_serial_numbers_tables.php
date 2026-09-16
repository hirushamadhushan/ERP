<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->timestamps();
        });
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->timestamps();
        });
        Schema::create('product_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('variation', 100)->default('Default');
            $table->string('serial_number', 100)->unique();
            $table->string('serial_key', 100)->unique();
            $table->string('status', 20)->default('available')->index();
            $table->unsignedBigInteger('sold_transaction_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serial_numbers');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('products');
    }
};
