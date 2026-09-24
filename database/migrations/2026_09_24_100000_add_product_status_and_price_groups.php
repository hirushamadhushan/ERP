<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->index());
        Schema::create('selling_price_groups', function (Blueprint $table) { $table->id(); $table->string('name',100)->unique(); $table->timestamps(); });
        Schema::create('product_selling_prices', function (Blueprint $table) { $table->id(); $table->foreignId('product_id')->constrained()->cascadeOnDelete(); $table->foreignId('selling_price_group_id')->constrained()->cascadeOnDelete(); $table->decimal('selling_price',18,4); $table->timestamps(); $table->unique(['product_id','selling_price_group_id']); });
    }
    public function down(): void { Schema::dropIfExists('product_selling_prices'); Schema::dropIfExists('selling_price_groups'); Schema::table('products', fn (Blueprint $table) => $table->dropColumn('is_active')); }
};
