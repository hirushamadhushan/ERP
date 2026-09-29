<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_points')->default(0)->after('return_balance');
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id(); $table->uuid('request_id')->unique();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('receipt_no', 60)->unique(); $table->decimal('amount', 15, 2);
            $table->string('payment_method', 40); $table->dateTime('paid_at')->index(); $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });

        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id(); $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('name', 255); $table->string('path', 500); $table->string('mime_type', 100); $table->unsignedBigInteger('size');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id(); $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->text('body'); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('customer_notes'); Schema::dropIfExists('customer_documents'); Schema::dropIfExists('customer_payments'); Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('reward_points')); }
};
