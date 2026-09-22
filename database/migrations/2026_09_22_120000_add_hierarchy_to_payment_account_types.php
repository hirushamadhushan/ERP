<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_account_types', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('payment_account_types')->restrictOnDelete();
        });
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->foreignId('payment_account_sub_type_id')->nullable()->after('payment_account_type_id')->constrained('payment_account_types')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) { $table->dropConstrainedForeignId('payment_account_sub_type_id'); });
        Schema::table('payment_account_types', function (Blueprint $table) { $table->dropConstrainedForeignId('parent_id'); });
    }
};
