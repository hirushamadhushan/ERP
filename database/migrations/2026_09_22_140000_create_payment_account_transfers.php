<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payment_account_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('from_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->decimal('amount',18,2);
            $table->dateTime('transferred_at')->index();
            $table->text('note')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('payment_account_transfers'); }
};
