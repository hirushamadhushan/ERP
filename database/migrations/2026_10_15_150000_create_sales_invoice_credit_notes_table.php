<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->decimal('credited_amount', 18, 2)->default(0)->after('paid_amount'));
        Schema::create('sales_invoice_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('credit_number', 80)->unique();
            $table->decimal('amount', 18, 2);
            $table->string('reason', 1000);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_credit_notes');
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->dropColumn('credited_amount'));
    }
};
