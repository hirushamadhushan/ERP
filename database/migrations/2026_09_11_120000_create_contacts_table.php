<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('contact_id', 60)->unique();
            $table->string('entity_type', 20)->default('individual');
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->string('customer_group')->nullable();
            $table->string('mobile', 50);
            $table->string('alternate_number', 50)->nullable();
            $table->string('landline', 50)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('tax_number', 100)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('advance_balance', 15, 2)->default(0);
            $table->decimal('due_balance', 15, 2)->default(0);
            $table->decimal('return_balance', 15, 2)->default(0);
            $table->unsignedInteger('pay_term')->nullable();
            $table->string('pay_term_unit', 10)->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->unsignedInteger('opening_due_cans')->default(0);
            $table->date('last_sale_at')->nullable();
            $table->decimal('commission_percentage', 5, 2)->default(0);
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('zip_code', 30)->nullable();
            $table->json('custom_fields')->nullable();
            $table->text('shipping_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
