<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
 public function up():void {
  Schema::table('products',function(Blueprint $t){$t->boolean('is_raw_material')->default(false)->after('track_lots')->index();$t->boolean('is_manufacturable')->default(false)->after('is_raw_material')->index();});
  Schema::create('manufacturing_orders',function(Blueprint $t){$t->id();$t->string('number',30)->unique();$t->foreignId('product_id')->constrained()->restrictOnDelete();$t->foreignId('location_id')->constrained()->restrictOnDelete();$t->foreignId('output_lot_id')->nullable()->constrained('product_lots')->restrictOnDelete();$t->foreignId('inventory_transaction_id')->nullable()->constrained()->restrictOnDelete();$t->decimal('quantity',18,4);$t->decimal('material_cost',18,4)->default(0);$t->decimal('expense_cost',18,4)->default(0);$t->decimal('total_cost',18,4)->default(0);$t->decimal('unit_cost',18,4)->default(0);$t->decimal('selling_price',18,4);$t->date('manufactured_at');$t->date('expires_at')->nullable();$t->string('status',20)->default('completed')->index();$t->text('notes')->nullable();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->dateTime('completed_at');$t->timestamps();});
  Schema::create('manufacturing_order_components',function(Blueprint $t){$t->id();$t->unsignedBigInteger('manufacturing_order_id');$t->unsignedBigInteger('product_id');$t->unsignedBigInteger('product_lot_id')->nullable();$t->decimal('quantity',18,4);$t->decimal('unit_cost',18,4);$t->decimal('total_cost',18,4);$t->foreign('manufacturing_order_id','mfg_component_order_fk')->references('id')->on('manufacturing_orders')->restrictOnDelete();$t->foreign('product_id','mfg_component_product_fk')->references('id')->on('products')->restrictOnDelete();$t->foreign('product_lot_id','mfg_component_lot_fk')->references('id')->on('product_lots')->restrictOnDelete();$t->index(['manufacturing_order_id','product_id'],'mfg_component_lookup');});
  Schema::create('manufacturing_order_expenses',function(Blueprint $t){$t->id();$t->unsignedBigInteger('manufacturing_order_id');$t->string('name',150);$t->decimal('amount',18,4);$t->foreign('manufacturing_order_id','mfg_expense_order_fk')->references('id')->on('manufacturing_orders')->restrictOnDelete();});
  foreach(DB::table('role_permissions')->whereIn('permission',['product.create','product.update','product.view'])->pluck('role_id')->unique() as $roleId){DB::table('role_permissions')->insertOrIgnore(['role_id'=>$roleId,'permission'=>'manufacturing.view']);DB::table('role_permissions')->insertOrIgnore(['role_id'=>$roleId,'permission'=>'manufacturing.process']);}
 }
 public function down():void {Schema::dropIfExists('manufacturing_order_expenses');Schema::dropIfExists('manufacturing_order_components');Schema::dropIfExists('manufacturing_orders');Schema::table('products',fn(Blueprint $t)=>$t->dropColumn(['is_raw_material','is_manufacturable']));}
};
