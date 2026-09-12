<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('calculation_type', 30)->default('percentage');
            $table->decimal('calculation_percentage', 7, 2)->default(0);
            $table->timestamps();
        });

        // Preserve existing customer group names without modifying contacts.
        DB::table('contacts')->where('type', 'customer')->whereNotNull('customer_group')
            ->where('customer_group', '<>', '')->select('customer_group')->distinct()
            ->orderBy('customer_group')->chunk(100, function ($groups) {
                foreach ($groups as $group) {
                    DB::table('customer_groups')->insertOrIgnore([
                        'name' => $group->customer_group, 'calculation_type' => 'percentage',
                        'calculation_percentage' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
