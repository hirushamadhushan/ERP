<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
