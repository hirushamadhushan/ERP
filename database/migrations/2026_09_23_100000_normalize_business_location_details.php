<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('location_code', 100)->nullable()->unique()->after('code');
        });

        Schema::create('location_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('value', 255);
            $table->unique(['location_id', 'type']);
            $table->timestamps();
        });

        Schema::create('location_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->unsignedTinyInteger('field_number');
            $table->string('value', 255)->nullable();
            $table->unique(['location_id', 'field_number']);
            $table->timestamps();
        });

        Schema::create('location_featured_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->unique(['location_id', 'product_id']);
            $table->timestamps();
        });

        Schema::create('location_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('method', 50);
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->unique(['location_id', 'method']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_payment_methods');
        Schema::dropIfExists('location_featured_products');
        Schema::dropIfExists('location_custom_field_values');
        Schema::dropIfExists('location_contacts');
        Schema::table('locations', fn (Blueprint $table) => $table->dropUnique(['location_code']));
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('location_code'));
    }
};
