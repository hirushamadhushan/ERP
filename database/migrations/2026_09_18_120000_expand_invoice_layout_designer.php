<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_layouts', function (Blueprint $table) {
            $table->string('design', 30)->default('classic');
            $table->longText('logo_path')->nullable();
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
        });
        Schema::create('invoice_layout_labels', function (Blueprint $table) {
            $table->id(); $table->foreignId('invoice_layout_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80); $table->string('value', 255)->nullable();
            $table->unique(['invoice_layout_id','key']);
        });
        Schema::create('invoice_layout_options', function (Blueprint $table) {
            $table->id(); $table->foreignId('invoice_layout_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80); $table->boolean('enabled')->default(false);
            $table->unique(['invoice_layout_id','key']);
        });
    }
    public function down(): void { Schema::dropIfExists('invoice_layout_options'); Schema::dropIfExists('invoice_layout_labels'); Schema::table('invoice_layouts', fn(Blueprint $table)=>$table->dropColumn(['design','logo_path','header_text','footer_text'])); }
};
