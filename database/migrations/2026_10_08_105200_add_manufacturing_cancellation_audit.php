<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('notes');
            $table->foreignId('cancelled_by')->nullable()->after('completed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['cancellation_reason', 'cancelled_by', 'cancelled_at']);
        });
    }
};
