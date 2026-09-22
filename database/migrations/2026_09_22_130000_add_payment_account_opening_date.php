<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('payment_accounts', fn (Blueprint $table) => $table->dateTime('opening_balance_at')->nullable()); }
    public function down(): void { Schema::table('payment_accounts', fn (Blueprint $table) => $table->dropColumn('opening_balance_at')); }
};
