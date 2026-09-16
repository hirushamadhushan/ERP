<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('short_name', 30)->unique();
            $table->boolean('allow_decimal');
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('base_unit_multiplier', 18, 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
