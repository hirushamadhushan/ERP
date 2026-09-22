<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('format', 20);
            $table->string('prefix', 30)->nullable();
            $table->unsignedBigInteger('start_number')->default(1);
            $table->unsignedBigInteger('current_number')->default(1);
            $table->unsignedTinyInteger('number_of_digits')->default(4);
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });
        Schema::create('invoice_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        $now = now();
        $schemeNames = DB::table('locations')->pluck('invoice_scheme')->filter()->unique()->values();
        if ($schemeNames->isEmpty()) $schemeNames = collect(['Default']);
        foreach ($schemeNames as $index => $name) DB::table('invoice_schemes')->insert(['name'=>$name,'format'=>'number','prefix'=>null,'start_number'=>1,'current_number'=>1,'number_of_digits'=>4,'is_default'=>$index === 0,'created_at'=>$now,'updated_at'=>$now]);
        $layoutNames = DB::table('locations')->pluck('invoice_layout_pos')->merge(DB::table('locations')->pluck('invoice_layout_sale'))->filter()->unique()->values();
        if ($layoutNames->isEmpty()) $layoutNames = collect(['Default']);
        foreach ($layoutNames as $index => $name) DB::table('invoice_layouts')->insert(['name'=>$name,'is_default'=>$index === 0,'created_at'=>$now,'updated_at'=>$now]);

        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('invoice_scheme_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_layout_pos_id')->nullable()->constrained('invoice_layouts')->restrictOnDelete();
            $table->foreignId('invoice_layout_sale_id')->nullable()->constrained('invoice_layouts')->restrictOnDelete();
        });
        foreach (DB::table('locations')->get(['id','invoice_scheme','invoice_layout_pos','invoice_layout_sale']) as $location) {
            DB::table('locations')->where('id', $location->id)->update([
                'invoice_scheme_id'=>DB::table('invoice_schemes')->where('name',$location->invoice_scheme)->value('id'),
                'invoice_layout_pos_id'=>DB::table('invoice_layouts')->where('name',$location->invoice_layout_pos)->value('id'),
                'invoice_layout_sale_id'=>DB::table('invoice_layouts')->where('name',$location->invoice_layout_sale)->value('id'),
            ]);
        }
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn(['invoice_scheme','invoice_layout_pos','invoice_layout_sale']));
    }

    public function down(): void { throw new RuntimeException('Restore from backup to reverse normalized invoice settings.'); }
};
