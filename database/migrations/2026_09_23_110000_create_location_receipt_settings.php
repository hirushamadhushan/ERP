<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('receipt_printers', function (Blueprint $table) {
            $table->id(); $table->string('name', 100)->unique(); $table->string('connection_type', 30)->default('network'); $table->string('connection_value', 255)->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('location_receipt_settings', function (Blueprint $table) {
            $table->id(); $table->foreignId('location_id')->unique()->constrained('locations')->cascadeOnDelete();
            $table->boolean('auto_print_invoice')->default(true);
            $table->string('printer_type', 30)->default('browser');
            $table->foreignId('receipt_printer_id')->nullable()->constrained('receipt_printers')->nullOnDelete();
            $table->foreignId('invoice_layout_id')->nullable()->constrained('invoice_layouts')->nullOnDelete();
            $table->foreignId('invoice_scheme_id')->nullable()->constrained('invoice_schemes')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('location_receipt_settings'); Schema::dropIfExists('receipt_printers'); }
};
