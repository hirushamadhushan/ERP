<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('business_settings',function(Blueprint $table){
        $table->string('invoice_receivable_account_code',30)->default('121000');
        $table->string('invoice_revenue_account_code',30)->default('400000');
        $table->string('invoice_tax_account_code',30)->default('220000');
        $table->string('invoice_advance_account_code',30)->default('210000');
    }); }
    public function down(): void { Schema::table('business_settings',fn(Blueprint $table)=>$table->dropColumn(['invoice_receivable_account_code','invoice_revenue_account_code','invoice_tax_account_code','invoice_advance_account_code'])); }
};
