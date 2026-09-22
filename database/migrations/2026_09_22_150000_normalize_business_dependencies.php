<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Preflight before any destructive DDL. Never silently choose between conflicting facts.
        foreach ([['payment_accounts','payment_account_types','payment_account_sub_type_id','payment_account_type_id'],['products','categories','subcategory_id','category_id']] as [$table,$parent,$child,$root]) {
            if (Schema::hasColumn($table,$child) && DB::table("$table as a")->join("$parent as b","b.id","=","a.$child")->where(fn($q)=>$q->whereNull('b.parent_id')->orWhereNull("a.$root")->orWhereColumn('b.parent_id','!=',"a.$root"))->exists()) {
                throw new RuntimeException("Resolve inconsistent $table hierarchy before migration.");
            }
        }
        if (DB::table('product_serial_numbers as s')->join('product_variants as v','v.id','=','s.product_variant_id')->whereNotNull('s.product_id')->whereColumn('s.product_id','!=','v.product_id')->exists()) throw new RuntimeException('Resolve inconsistent serial/variant assignments before migration.');

        foreach ([['payment_accounts','selected_type_id','payment_account_types','payment_account_sub_type_id','payment_account_type_id'],['products','selected_category_id','categories','subcategory_id','category_id']] as [$table,$selected,$target,$child,$root]) {
            if (!Schema::hasColumn($table,$selected)) Schema::table($table,fn(Blueprint $t)=>$t->foreignId($selected)->nullable()->constrained($target)->restrictOnDelete());
            if (Schema::hasColumn($table,$child)) {
                DB::table($table)->update([$selected=>DB::raw("COALESCE($child, $root)")]);
                Schema::table($table,function(Blueprint $t) use($child,$root){$t->dropConstrainedForeignId($child);$t->dropConstrainedForeignId($root);});
            }
        }

        foreach (['product'=>'products','contact'=>'contacts'] as $entity=>$table) {
            $child=$entity.'_custom_field_values'; $fk=$entity.'_id';
            if (!Schema::hasTable($child)) Schema::create($child,function(Blueprint $t) use($fk,$table){
                $t->foreignId($fk)->constrained($table)->cascadeOnDelete();
                $t->string('field_key',128);
                $t->text('value')->nullable();
                $t->unsignedInteger('display_order');
                $t->primary([$fk,'field_key']);
            });
            if (Schema::hasColumn($table,'custom_fields')) {
                foreach (DB::table($table)->select('id','custom_fields')->orderBy('id')->cursor() as $record) {
                    $values=json_decode($record->custom_fields ?? '[]',true,512,JSON_THROW_ON_ERROR) ?? [];
                    if (!is_array($values)) throw new RuntimeException("Invalid custom fields in $table #$record->id");
                    $position=0;
                    foreach ($values as $key=>$value) {
                        if ($value!==null && !is_string($value)) throw new RuntimeException("Non-string custom field in $table #$record->id");
                        DB::table($child)->updateOrInsert([$fk=>$record->id,'field_key'=>(string)$key],['value'=>$value,'display_order'=>$position++]);
                    }
                }
                Schema::table($table,fn(Blueprint $t)=>$t->dropColumn('custom_fields'));
            }
        }
        foreach (['products','product_variants'] as $table) {
            foreach (['purchase_price_inc','margin'] as $column) if (Schema::hasColumn($table,$column)) Schema::table($table,fn(Blueprint $t)=>$t->dropColumn($column));
        }
        // Disjoint assignments: direct product OR variant; never duplicate the variant's product.
        Schema::table('product_serial_numbers',fn(Blueprint $t)=>$t->unsignedBigInteger('product_id')->nullable()->change());
        DB::table('product_serial_numbers')->whereNotNull('product_variant_id')->update(['product_id'=>null]);
        if (DB::getDriverName()==='sqlite') {
            foreach (['INSERT','UPDATE'] as $event) DB::unprepared("CREATE TRIGGER IF NOT EXISTS serial_assignment_".strtolower($event)." BEFORE $event ON product_serial_numbers WHEN (NEW.product_id IS NULL) = (NEW.product_variant_id IS NULL) BEGIN SELECT RAISE(ABORT, 'Select exactly one serial product or variant'); END");
        } elseif (!DB::selectOne("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'product_serial_numbers' AND CONSTRAINT_NAME = 'serial_assignment_exclusive'")) {
            DB::statement('ALTER TABLE product_serial_numbers ADD CONSTRAINT serial_assignment_exclusive CHECK ((product_id IS NULL) <> (product_variant_id IS NULL))');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restore the pre-normalization backup to reverse this data migration.');
    }
};
