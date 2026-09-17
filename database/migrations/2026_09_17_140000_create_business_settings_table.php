<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name')->default('Codeza POS');
            $table->string('start_date')->default('2015-01-01');
            $table->decimal('default_profit_percent', 8, 2)->default(25.00);
            $table->string('currency')->default('Sri Lanka - Rupees(LKR)');
            $table->string('currency_symbol_placement')->default('Before amount');
            $table->string('time_zone')->default('Asia/Kolkata');
            $table->longText('logo_path')->nullable();
            $table->string('financial_year_start_month')->default('January');
            $table->string('stock_accounting_method')->default('FIFO (First In First Out)');
            $table->integer('transaction_edit_days')->default(30);
            $table->string('date_format')->default('mm/dd/yyyy');
            $table->string('time_format')->default('24 Hour');
            $table->integer('currency_precision')->default(2);
            $table->integer('quantity_precision')->default(2);
            $table->json('other_settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
