<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_consignments', function (Blueprint $table) {
            $table->index('loading_transfer_id', 'delivery_consignments_loading_transfer_index');
        });
        Schema::table('delivery_consignment_lines', function (Blueprint $table) {
            $table->index('transfer_line_id', 'delivery_consignment_lines_transfer_line_index');
        });
        Schema::table('delivery_consignments', fn (Blueprint $table) => $table->dropUnique(['loading_transfer_id']));
        Schema::table('delivery_consignment_lines', fn (Blueprint $table) => $table->dropUnique(['transfer_line_id']));
        Schema::table('delivery_consignments', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancel_reason', 500)->nullable();
        });
        Schema::create('delivery_consignment_serial_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consignment_line_id');
            $table->unsignedBigInteger('serial_id');
            $table->string('outcome', 16)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unique(['consignment_line_id', 'serial_id'], 'delivery_serial_outcome_unique');
            $table->foreign('consignment_line_id', 'dcso_consignment_line_fk')->references('id')->on('delivery_consignment_lines')->cascadeOnDelete();
            $table->foreign('serial_id', 'dcso_serial_fk')->references('id')->on('product_serial_numbers')->restrictOnDelete();
        });

        $newPermissions = ['delivery.create', 'delivery.dispatch', 'delivery.arrive', 'delivery.pod', 'delivery.correct'];
        $roleIds = DB::table('role_permissions')->where('permission', 'delivery.transfer')->pluck('role_id');
        foreach ($roleIds as $roleId) foreach ($newPermissions as $permission) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission' => $permission]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', ['delivery.create', 'delivery.dispatch', 'delivery.arrive', 'delivery.pod', 'delivery.correct'])->delete();
        Schema::dropIfExists('delivery_consignment_serial_outcomes');
        Schema::table('delivery_consignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
        Schema::table('delivery_consignment_lines', fn (Blueprint $table) => $table->unique('transfer_line_id'));
        Schema::table('delivery_consignment_lines', fn (Blueprint $table) => $table->dropIndex('delivery_consignment_lines_transfer_line_index'));
        Schema::table('delivery_consignments', fn (Blueprint $table) => $table->unique('loading_transfer_id'));
        Schema::table('delivery_consignments', fn (Blueprint $table) => $table->dropIndex('delivery_consignments_loading_transfer_index'));
    }
};
