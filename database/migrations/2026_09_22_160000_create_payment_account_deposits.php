<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('payment_account_deposits', function(Blueprint $t) {
        $t->id(); $t->uuid('request_id')->unique();
        $t->foreignId('account_id')->constrained('payment_accounts')->restrictOnDelete();
        $t->foreignId('from_account_id')->nullable()->constrained('payment_accounts')->restrictOnDelete();
        $t->decimal('amount',18,2); $t->dateTime('deposited_at')->index(); $t->text('note')->nullable();
        $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('payment_account_deposits'); }
};
