<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_serial_numbers', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable()->after('sold_transaction_id');
            $table->unsignedBigInteger('sold_sell_line_id')->nullable()->after('sold_at');
        });
        Schema::create('product_serial_number_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('prefix', 100)->nullable();
            $table->string('middle_fix', 100)->nullable();
            $table->string('post_fix', 100)->nullable();
            $table->unsignedBigInteger('start_number');
            $table->unsignedInteger('quantity');
            $table->unsignedSmallInteger('padding');
            $table->string('barcode_format', 20);
            $table->decimal('paper_width_mm', 8, 2);
            $table->decimal('label_width_mm', 8, 2);
            $table->decimal('label_height_mm', 8, 2);
            $table->timestamps();
        });
        Schema::create('sell_line_serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sell_line_id');
            $table->foreignId('product_serial_number_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index('sell_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_line_serial_numbers');
        Schema::dropIfExists('product_serial_number_generations');
        Schema::table('product_serial_numbers', fn (Blueprint $table) => $table->dropColumn(['sold_at', 'sold_sell_line_id']));
    }
};
