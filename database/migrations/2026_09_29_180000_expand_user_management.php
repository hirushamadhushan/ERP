<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('prefix', 20)->nullable()->after('username');
            $table->string('first_name')->nullable()->after('prefix');
            $table->string('last_name')->nullable()->after('first_name');
            $table->boolean('allow_login')->default(true)->after('status');
            $table->boolean('all_locations')->default(true)->after('allow_login');
            $table->boolean('restrict_contacts')->default(false)->after('all_locations');
            $table->decimal('commission_percent', 5, 2)->default(0)->after('restrict_contacts');
            $table->decimal('max_sales_discount_percent', 5, 2)->default(0)->after('commission_percent');
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('alternate_contact', 40)->nullable();
            $table->string('family_contact', 40)->nullable();
            $table->string('facebook_link')->nullable();
            $table->string('twitter_link')->nullable();
            $table->string('social_media_1')->nullable();
            $table->string('social_media_2')->nullable();
            $table->string('custom_field_1')->nullable();
            $table->string('custom_field_2')->nullable();
            $table->string('custom_field_3')->nullable();
            $table->string('custom_field_4')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('id_proof_name')->nullable();
            $table->string('id_proof_number')->nullable();
            $table->text('permanent_address')->nullable();
            $table->text('current_address')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_identifier_code', 100)->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('tax_payer_id', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('location_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'location_id']);
        });

        Schema::create('contact_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_user');
        Schema::dropIfExists('location_user');
        Schema::dropIfExists('user_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'first_name', 'last_name', 'allow_login', 'all_locations',
                'restrict_contacts', 'commission_percent', 'max_sales_discount_percent']);
        });
    }
};
