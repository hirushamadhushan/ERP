<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Normalized up to 4NF (Fourth Normal Form):
     * - 1NF: Atomic attributes, unique primary keys, no repeating groups.
     * - 2NF: No partial key dependencies; non-keys fully dependent on primary key.
     * - 3NF: No transitive dependencies; unit, ledgers, and creator reference canonical foreign tables.
     * - BCNF: Every determinant is a candidate key (id, code).
     * - 4NF: Multi-valued dependencies decomposed into service_consumables with composite unique constraint.
     */
    public function up(): void
    {
        if (!Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
                $table->decimal('unit_cost', 15, 2)->default(0.00);
                $table->decimal('unit_rate', 15, 2)->default(0.00);
                $table->foreignId('cost_ledger_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
                $table->foreignId('revenue_ledger_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['name']);
            });
        }

        if (!Schema::hasTable('service_consumables')) {
            Schema::create('service_consumables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('quantity', 12, 4)->default(1.0000);
                $table->timestamps();

                // 4NF Multi-valued dependency integrity constraint
                $table->unique(['service_id', 'product_id'], 'srv_consumable_service_product_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_consumables');
        Schema::dropIfExists('services');
    }
};
