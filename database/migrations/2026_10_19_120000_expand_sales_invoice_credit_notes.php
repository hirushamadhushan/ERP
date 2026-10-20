<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('sales_invoice_credit_notes','credit_date')) Schema::table('sales_invoice_credit_notes',function(Blueprint $table){
            $table->date('credit_date')->nullable()->after('credit_number');
            $table->string('status',20)->default('posted')->after('credit_date');
            $table->decimal('untaxed_amount',18,2)->default(0)->after('amount');
            $table->decimal('tax_amount',18,2)->default(0)->after('untaxed_amount');
        });
        // A failed MySQL DDL statement can leave the newly-created table behind because
        // schema changes are not transactional. These tables contain no data until this
        // migration completes, so safely clean up that partial state before retrying.
        Schema::dropIfExists('sales_invoice_credit_note_journal_items');
        Schema::dropIfExists('sales_invoice_credit_note_lines');
        Schema::create('sales_invoice_credit_note_lines',function(Blueprint $table){
            $table->id();$table->foreignId('sales_invoice_credit_note_id');
            $table->foreignId('sales_invoice_line_id')->nullable();
            $table->text('description');$table->decimal('quantity',18,4)->default(0);$table->decimal('unit_price',18,4)->default(0);
            $table->decimal('tax_rate',8,3)->default(0);$table->decimal('subtotal',18,2);$table->decimal('tax_amount',18,2);$table->decimal('total',18,2);$table->timestamps();
            $table->foreign('sales_invoice_credit_note_id','cn_lines_note_fk')->references('id')->on('sales_invoice_credit_notes')->cascadeOnDelete();
            $table->foreign('sales_invoice_line_id','cn_lines_invoice_line_fk')->references('id')->on('sales_invoice_lines')->nullOnDelete();
        });
        Schema::create('sales_invoice_credit_note_journal_items',function(Blueprint $table){
            $table->id();$table->foreignId('sales_invoice_credit_note_id');
            $table->string('account_code',30);$table->string('account_name');$table->string('label');
            $table->decimal('debit',18,2)->default(0);$table->decimal('credit',18,2)->default(0);$table->timestamps();
            $table->foreign('sales_invoice_credit_note_id','cn_journal_note_fk')->references('id')->on('sales_invoice_credit_notes')->cascadeOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_credit_note_journal_items');Schema::dropIfExists('sales_invoice_credit_note_lines');
        Schema::table('sales_invoice_credit_notes',fn(Blueprint $table)=>$table->dropColumn(['credit_date','status','untaxed_amount','tax_amount']));
    }
};
