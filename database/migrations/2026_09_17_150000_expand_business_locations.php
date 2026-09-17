<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('landmark')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code', 30)->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('price_group')->nullable();
            $table->string('invoice_scheme')->default('Default');
            $table->string('invoice_layout_pos')->default('Default');
            $table->string('invoice_layout_sale')->default('Default');
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'landmark', 'city', 'zip_code', 'state', 'country', 'price_group',
                'invoice_scheme', 'invoice_layout_pos', 'invoice_layout_sale', 'is_active',
            ]);
        });
    }
};
