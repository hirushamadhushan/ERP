<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_returns', function (Blueprint $table) {
            $table->foreignId('unloading_transfer_id')->nullable()->unique()->constrained('delivery_transfers')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unloading_transfer_id');
            $table->dropColumn('completed_at');
        });
    }
};
