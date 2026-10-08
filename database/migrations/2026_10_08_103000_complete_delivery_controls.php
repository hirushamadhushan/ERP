<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_quarantine_locations', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->primary()->constrained('locations')->restrictOnDelete();
            $table->foreignId('location_id')->unique()->constrained('locations')->restrictOnDelete();
        });
        Schema::create('delivery_damage_dispositions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('consignment_line_id')->unique();
            $table->unsignedBigInteger('quarantine_location_id');
            $table->unsignedBigInteger('inventory_transaction_id');
            $table->decimal('quantity',18,4); $table->string('status',20)->default('quarantined'); $table->timestamps();
            $table->foreign('consignment_line_id', 'damage_line_fk')->references('id')->on('delivery_consignment_lines')->restrictOnDelete();
            $table->foreign('quarantine_location_id', 'damage_location_fk')->references('id')->on('locations')->restrictOnDelete();
            $table->foreign('inventory_transaction_id', 'damage_transaction_fk')->references('id')->on('inventory_transactions')->restrictOnDelete();
        });
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id(); $table->string('number',40)->unique(); $table->foreignId('vehicle_id')->constrained('delivery_vehicles')->restrictOnDelete();
            $table->string('status',20)->default('planned')->index(); $table->dateTime('scheduled_at')->nullable(); $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
        });
        Schema::create('delivery_route_stops', function (Blueprint $table) {
            $table->id(); $table->foreignId('route_id')->constrained('delivery_routes')->cascadeOnDelete();
            $table->foreignId('consignment_id')->unique()->constrained('delivery_consignments')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence'); $table->string('status',20)->default('pending');
            $table->unique(['route_id','sequence']);
        });
        Schema::create('delivery_pod_corrections', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('consignment_id');
            $table->string('status',20)->default('pending')->index(); $table->string('reason',500);
            $table->json('original_outcome'); $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable(); $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('reversal_transaction_id')->nullable(); $table->timestamps();
            $table->foreign('consignment_id', 'pod_correction_consignment_fk')->references('id')->on('delivery_consignments')->restrictOnDelete();
            $table->foreign('requested_by', 'pod_correction_requester_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by', 'pod_correction_approver_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reversal_transaction_id', 'pod_correction_reversal_fk')->references('id')->on('inventory_transactions')->restrictOnDelete();
        });
        Schema::table('delivery_consignments', function (Blueprint $table) {
            $table->string('receiver_id_reference',100)->nullable(); $table->decimal('receiver_latitude',10,7)->nullable();
            $table->decimal('receiver_longitude',10,7)->nullable();
        });
        Schema::table('delivery_consignment_proofs', function (Blueprint $table) {
            $table->string('caption',250)->nullable(); $table->string('checksum',64)->nullable()->index();
            $table->dateTime('retention_until')->nullable(); $table->dateTime('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE delivery_consignments ADD CONSTRAINT delivery_status_check CHECK (status IN ('loaded','in_transit','arrived','delivered','partial','failed','cancelled'))");
            DB::statement("ALTER TABLE delivery_consignment_lines ADD CONSTRAINT delivery_quantities_nonnegative CHECK (delivered_quantity >= 0 AND damaged_quantity >= 0 AND missing_quantity >= 0)");
            DB::statement("ALTER TABLE delivery_consignment_proofs ADD CONSTRAINT delivery_proof_kind_check CHECK (kind IN ('photo','signature'))");
            DB::statement("ALTER TABLE delivery_consignment_events ADD CONSTRAINT delivery_event_check CHECK (event IN ('loaded','departed','arrived','delivered','partial','failed','proof_uploaded','proof_deleted','rescheduled','cancelled','correction_requested','pod_reopened'))");
        }
        foreach (DB::table('role_permissions')->where('permission','delivery.correct')->pluck('role_id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id'=>$roleId,'permission'=>'delivery.proof.manage']);
            DB::table('role_permissions')->insertOrIgnore(['role_id'=>$roleId,'permission'=>'delivery.route.manage']);
        }
    }
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach (['delivery_status_check'=>'delivery_consignments','delivery_quantities_nonnegative'=>'delivery_consignment_lines','delivery_proof_kind_check'=>'delivery_consignment_proofs','delivery_event_check'=>'delivery_consignment_events'] as $constraint=>$table) DB::statement("ALTER TABLE $table DROP CHECK $constraint");
        }
        Schema::table('delivery_consignment_proofs', function(Blueprint $t){$t->dropConstrainedForeignId('deleted_by');$t->dropColumn(['caption','checksum','retention_until','deleted_at']);});
        Schema::table('delivery_consignments', fn(Blueprint $t)=>$t->dropColumn(['receiver_id_reference','receiver_latitude','receiver_longitude']));
        Schema::dropIfExists('delivery_pod_corrections'); Schema::dropIfExists('delivery_route_stops'); Schema::dropIfExists('delivery_routes');
        Schema::dropIfExists('delivery_damage_dispositions'); Schema::dropIfExists('delivery_quarantine_locations');
    }
};
