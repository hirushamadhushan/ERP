<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The guard also makes the migration safe if an earlier development build
        // created these tables under a later timestamp.
        if (Schema::hasTable('bills_of_materials')) return;
        Schema::create('bills_of_materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('output_quantity', 18, 4)->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('bill_of_material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_material_id')->constrained('bills_of_materials')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->unsignedInteger('position')->default(0);
            $table->unique(['bill_of_material_id', 'product_id'], 'bom_product_unique');
        });

        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->foreignId('bill_of_material_id')->nullable()->after('product_id')->constrained('bills_of_materials')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('started_by')->nullable()->after('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->after('started_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmed_at')->nullable()->after('completed_at');
            $table->dateTime('started_at')->nullable()->after('confirmed_at');
        });

        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->dateTime('completed_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->dropForeign(['bill_of_material_id']);
            $table->dropForeign(['confirmed_by']);
            $table->dropForeign(['started_by']);
            $table->dropForeign(['completed_by']);
            $table->dropColumn(['bill_of_material_id', 'confirmed_by', 'started_by', 'completed_by', 'confirmed_at', 'started_at']);
        });
        Schema::dropIfExists('bill_of_material_items');
        Schema::dropIfExists('bills_of_materials');
    }
};
