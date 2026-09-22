<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_account_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('account_number', 80)->unique();
            $table->foreignId('payment_account_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('payment_account_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('value', 255);
            $table->unsignedTinyInteger('display_order')->default(1);
            $table->timestamps();
            $table->unique(['payment_account_id', 'label']);
        });
        $now = now();
        DB::table('payment_account_types')->insert(collect(['Cash', 'Bank Account', 'Card', 'Mobile Wallet'])->map(fn ($name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now])->all());
    }
    public function down(): void { Schema::dropIfExists('payment_account_details'); Schema::dropIfExists('payment_accounts'); Schema::dropIfExists('payment_account_types'); }
};
