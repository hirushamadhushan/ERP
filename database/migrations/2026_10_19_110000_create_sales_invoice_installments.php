<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount',18,2);
            $table->decimal('settled_amount',18,2)->default(0);
            $table->string('status',20)->default('pending')->index();
            $table->timestamps();
            $table->unique(['sales_invoice_id','sequence']);
        });
        Schema::table('sales_invoice_journal_items', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_journal_items', fn(Blueprint $table)=>$table->dropColumn('due_date'));
        Schema::dropIfExists('sales_invoice_installments');
    }
};
