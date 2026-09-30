<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notes', function (Blueprint $table) {
            $table->string('heading')->nullable()->after('user_id');
            $table->boolean('is_private')->default(false)->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('user_notes', function (Blueprint $table) {
            $table->dropColumn(['heading', 'is_private']);
        });
    }
};
