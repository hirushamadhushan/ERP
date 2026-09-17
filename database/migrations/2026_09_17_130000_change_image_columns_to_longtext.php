<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->longText('image_path')->nullable()->change();
            $table->longText('variant_image_path')->nullable()->change();
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->longText('image_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->change();
            $table->string('variant_image_path')->nullable()->change();
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('image_path')->nullable()->change();
        });
    }
};
