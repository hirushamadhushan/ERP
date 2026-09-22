<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL commits DDL statements individually. This also resumes safely if a
        // previous run was interrupted after the new tables were created.
        if (Schema::hasTable('role_permissions')) {
            $this->finishNormalization();
            return;
        }
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 100);
            $table->unique(['role_id', 'permission']);
        });
        Schema::create('variation_template_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variation_template_id')->constrained()->cascadeOnDelete();
            $table->string('value', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['variation_template_id', 'value']);
        });
        Schema::create('business_product_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_setting_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sku_prefix', 30)->nullable();
            $table->boolean('expiry_enabled')->default(false);
            $table->string('expiry_mode', 30)->default('item_expiry');
            $table->string('on_expiry', 30)->default('keep_selling');
            $table->unsignedInteger('expiry_grace_days')->default(0);
            $table->foreignId('default_unit_id')->nullable()->constrained('units')->nullOnDelete();
            foreach (['enable_brands', 'enable_categories', 'enable_subcategories', 'enable_price_tax', 'enable_our_price', 'enable_sub_units', 'enable_racks', 'enable_row', 'enable_position', 'enable_warranty', 'enable_secondary_unit', 'enable_serial_numbers'] as $column) {
                $table->boolean($column)->default(in_array($column, ['enable_brands', 'enable_categories', 'enable_subcategories', 'enable_price_tax'], true));
            }
            $table->timestamps();
        });

        Schema::table('users', fn (Blueprint $table) => $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('contacts', fn (Blueprint $table) => $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete());
        Schema::table('product_variants', fn (Blueprint $table) => $table->foreignId('variation_template_value_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('product_serial_numbers', fn (Blueprint $table) => $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete());

        $now = now();
        foreach (DB::table('users')->select('role')->distinct()->pluck('role')->filter() as $name) {
            if (! DB::table('roles')->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
                DB::table('roles')->insert(['name' => $name, 'description' => null, 'permissions' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach (DB::table('users')->get(['id', 'role']) as $user) {
            $roleId = DB::table('roles')->whereRaw('LOWER(name) = ?', [strtolower($user->role)])->value('id');
            DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
        }
        foreach (DB::table('roles')->get(['id', 'permissions']) as $role) {
            foreach ((array) json_decode($role->permissions ?: '[]', true) as $permission) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission' => $permission]);
            }
        }
        foreach (DB::table('contacts')->whereNotNull('customer_group')->select('customer_group')->distinct()->pluck('customer_group')->filter() as $name) {
            if (! DB::table('customer_groups')->where('name', $name)->exists()) {
                DB::table('customer_groups')->insert(['name' => $name, 'calculation_type' => 'percentage', 'calculation_percentage' => 0, 'selling_price_group' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach (DB::table('contacts')->whereNotNull('customer_group')->get(['id', 'customer_group']) as $contact) {
            DB::table('contacts')->where('id', $contact->id)->update(['customer_group_id' => DB::table('customer_groups')->where('name', $contact->customer_group)->value('id')]);
        }
        foreach (DB::table('variation_templates')->get(['id', 'values']) as $template) {
            foreach (array_values((array) json_decode($template->values ?: '[]', true)) as $order => $value) {
                DB::table('variation_template_values')->insertOrIgnore(['variation_template_id' => $template->id, 'value' => $value, 'sort_order' => $order]);
            }
        }
        foreach (DB::table('product_variants')->get(['id', 'variation_template_id', 'value']) as $variant) {
            $valueId = DB::table('variation_template_values')->where('variation_template_id', $variant->variation_template_id)->where('value', $variant->value)->value('id');
            DB::table('product_variants')->where('id', $variant->id)->update(['variation_template_value_id' => $valueId]);
        }
        foreach (DB::table('product_serial_numbers')->where('variation', '!=', 'Default')->get(['id', 'product_id', 'variation']) as $serial) {
            $variantId = DB::table('product_variants')->join('variation_template_values', 'variation_template_values.id', '=', 'product_variants.variation_template_value_id')->where('product_variants.product_id', $serial->product_id)->where('variation_template_values.value', $serial->variation)->value('product_variants.id');
            DB::table('product_serial_numbers')->where('id', $serial->id)->update(['product_variant_id' => $variantId]);
        }
        foreach (DB::table('business_settings')->get(['id', 'other_settings']) as $setting) {
            $product = (array) ((json_decode($setting->other_settings ?: '{}', true)['product'] ?? []));
            DB::table('business_product_settings')->insert(array_merge([
                'business_setting_id' => $setting->id, 'sku_prefix' => null, 'expiry_enabled' => false,
                'expiry_mode' => 'item_expiry', 'on_expiry' => 'keep_selling', 'expiry_grace_days' => 0,
                'default_unit_id' => null, 'enable_brands' => true, 'enable_categories' => true,
                'enable_subcategories' => true, 'enable_price_tax' => true, 'enable_our_price' => false,
                'enable_sub_units' => false, 'enable_racks' => false, 'enable_row' => false,
                'enable_position' => false, 'enable_warranty' => false, 'enable_secondary_unit' => false,
                'enable_serial_numbers' => false, 'created_at' => $now, 'updated_at' => $now,
            ], array_intersect_key($product, array_flip(['sku_prefix','expiry_enabled','expiry_mode','on_expiry','expiry_grace_days','default_unit_id','enable_brands','enable_categories','enable_subcategories','enable_price_tax','enable_our_price','enable_sub_units','enable_racks','enable_row','enable_position','enable_warranty','enable_secondary_unit','enable_serial_numbers']))));
        }

        $this->finishNormalization();
    }

    private function finishNormalization(): void
    {
        foreach ([
            'users' => 'role', 'roles' => 'permissions', 'contacts' => 'customer_group',
            'product_serial_numbers' => 'variation',
        ] as $tableName => $column) {
            if (Schema::hasColumn($tableName, $column)) Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
        }

        if (Schema::hasColumn('product_variants', 'variation_template_id')) {
            // The old composite unique index can be selected by MySQL to support
            // product_id's FK. Give that FK a dedicated index before removing it.
            Schema::table('product_variants', fn (Blueprint $table) => $table->index('product_id', 'product_variants_product_id_normalization_index'));
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropUnique(['product_id', 'variation_template_id', 'value']);
                $table->dropConstrainedForeignId('variation_template_id');
                $table->dropColumn('value');
                $table->unique(['product_id', 'variation_template_value_id']);
            });
        }
        if (Schema::hasColumn('variation_templates', 'values')) Schema::table('variation_templates', fn (Blueprint $table) => $table->dropColumn('values'));
        if (Schema::hasColumn('business_settings', 'other_settings')) Schema::table('business_settings', fn (Blueprint $table) => $table->dropColumn('other_settings'));
    }

    public function down(): void
    {
        throw new RuntimeException('Core relationship normalization cannot be safely reversed without losing relational data. Restore from backup instead.');
    }
};
