<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->decimal('paid_amount', 18, 2)->default(0)->after('total_amount');
            $table->decimal('balance_due', 18, 2)->default(0)->after('paid_amount');
        });
        Schema::create('sales_invoice_journal_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 30); $table->string('account_name'); $table->string('label');
            $table->decimal('debit', 18, 2)->default(0); $table->decimal('credit', 18, 2)->default(0); $table->timestamps();
        });
        Schema::create('sales_invoice_payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->string('payment_number', 80)->unique(); $table->date('payment_date');
            $table->decimal('amount', 18, 2); $table->string('method', 60)->nullable();
            $table->string('reference')->nullable(); $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
        });
        Schema::create('sales_invoice_activities', function (Blueprint $table) {
            $table->id(); $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('system'); $table->text('message');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_activities'); Schema::dropIfExists('sales_invoice_payments');
        Schema::dropIfExists('sales_invoice_journal_items');
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->dropColumn(['paid_amount','balance_due']));
    }
};
