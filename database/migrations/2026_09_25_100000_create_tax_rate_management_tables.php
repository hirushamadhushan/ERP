<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('amount', 8, 3)->default(0);
            $table->boolean('is_tax_group')->default(false)->index();
            $table->boolean('for_tax_group')->default(false)->index();
            $table->timestamps();
        });

        // A tax group can contain many independent taxes. The composite key
        // prevents duplicate membership and keeps the multi-valued fact in 4NF.
        Schema::create('group_sub_taxes', function (Blueprint $table) {
            $table->foreignId('group_tax_id')->constrained('tax_rates')->restrictOnDelete();
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->restrictOnDelete();
            $table->primary(['group_tax_id', 'tax_rate_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('product_type')->constrained('tax_rates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('tax_rate_id'));
        Schema::dropIfExists('group_sub_taxes');
        Schema::dropIfExists('tax_rates');
    }
};
