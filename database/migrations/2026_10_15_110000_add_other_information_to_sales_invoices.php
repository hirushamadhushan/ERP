<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->foreignId('salesperson_id')->nullable()->after('commission_agent_id')->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_bank_account_id')->nullable()->after('salesperson_id')->constrained('payment_accounts')->nullOnDelete();
            $table->string('payment_reference', 255)->nullable()->after('customer_reference');
            $table->date('delivery_date')->nullable()->after('due_date');
            $table->string('incoterm', 100)->nullable();
            $table->string('incoterm_location', 255)->nullable();
            $table->string('fiscal_position', 255)->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->boolean('auto_post')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salesperson_id');
            $table->dropConstrainedForeignId('recipient_bank_account_id');
            $table->dropColumn(['payment_reference','delivery_date','incoterm','incoterm_location','fiscal_position','payment_method','auto_post']);
        });
    }
};
