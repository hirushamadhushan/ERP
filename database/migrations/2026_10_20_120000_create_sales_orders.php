<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('sales_orders',function(Blueprint $t){$t->id();$t->string('order_number')->unique();$t->foreignId('customer_id')->constrained('contacts')->restrictOnDelete();$t->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();$t->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();$t->foreignId('commission_agent_id')->nullable()->constrained('contacts')->nullOnDelete();$t->date('order_date');$t->date('delivery_date')->nullable();$t->string('status',20)->default('open')->index();$t->decimal('total_amount',18,2)->default(0);$t->text('terms')->nullable();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->timestamps();});
  Schema::create('sales_order_lines',function(Blueprint $t){$t->id();$t->foreignId('sales_order_id')->constrained()->cascadeOnDelete();$t->foreignId('product_id')->constrained('products')->restrictOnDelete();$t->foreignId('product_lot_id')->nullable()->constrained('product_lots')->restrictOnDelete();$t->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->restrictOnDelete();$t->text('description');$t->decimal('quantity',18,4);$t->decimal('unit_price',18,4);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();});
  Schema::table('sales_invoices',fn(Blueprint $t)=>$t->foreignId('sales_order_id')->nullable()->after('invoice_number')->constrained('sales_orders')->nullOnDelete());
 }
 public function down():void {Schema::table('sales_invoices',fn(Blueprint $t)=>$t->dropConstrainedForeignId('sales_order_id'));Schema::dropIfExists('sales_order_lines');Schema::dropIfExists('sales_orders');}
};
