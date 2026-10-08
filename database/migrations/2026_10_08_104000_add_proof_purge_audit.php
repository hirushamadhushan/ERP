<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('delivery_consignment_proofs',fn(Blueprint $table)=>$table->dateTime('purged_at')->nullable()->after('deleted_by')); }
    public function down(): void { Schema::table('delivery_consignment_proofs',fn(Blueprint $table)=>$table->dropColumn('purged_at')); }
};
