<?php
namespace Tests\Feature;
use App\Models\{BillOfMaterial,Location,Product,ProductSerialNumber,Unit,User};
use App\Services\ManufacturingService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManufacturingTest extends TestCase {
 public function createApplication(){$app=parent::createApplication();$app['config']->set('database.default','sqlite');$app['config']->set('database.connections.sqlite.database',':memory:');return $app;}
 protected function setUp():void{parent::setUp();$this->artisan('migrate',['--force'=>true])->assertExitCode(0);}
 private function fixtures():array{
  $user=User::factory()->create(['all_locations'=>true]);$this->actingAs($user);$location=Location::create(['name'=>'Main Factory','code'=>'FACT','is_active'=>true]);$unit=Unit::create(['name'=>'Pieces','short_name'=>'Pc','allow_decimal'=>false]);
  $raw=[];foreach([['Rice',100,4],['Bag',20,2]] as [$name,$stock,$cost]){$p=Product::create(['name'=>$name,'code'=>strtoupper($name),'unit_id'=>$unit->id,'is_active'=>true,'manage_stock'=>true,'is_raw_material'=>true,'is_manufacturable'=>false,'track_lots'=>false,'enable_serial'=>false,'product_type'=>'single','purchase_price'=>$cost,'selling_price'=>$cost]);$p->locations()->attach($location->id,['opening_quantity'=>$stock]);$raw[]=$p;}
  $output=Product::create(['name'=>'Packed Rice','code'=>'PACK-RICE','unit_id'=>$unit->id,'is_active'=>true,'manage_stock'=>true,'is_raw_material'=>false,'is_manufacturable'=>true,'track_lots'=>true,'enable_serial'=>false,'product_type'=>'single','purchase_price'=>0,'selling_price'=>40]);$output->locations()->attach($location->id,['opening_quantity'=>0]);
  $bom=BillOfMaterial::create(['code'=>'BOM-PACK-RICE-01','product_id'=>$output->id,'output_quantity'=>10,'is_active'=>true,'created_by'=>$user->id]);$bom->items()->createMany([['product_id'=>$raw[0]->id,'quantity'=>25,'position'=>0],['product_id'=>$raw[1]->id,'quantity'=>10,'position'=>1]]);
  return [$user,$location,$raw,$output,$bom];
 }
 public function test_recipe_scales_and_lifecycle_posts_stock_only_on_completion():void{
  [$user,$location,$raw,$output,$bom]=$this->fixtures();$this->get('/manufacturing/create')->assertOk()->assertSee('Product materials')->assertSee('Review & draft',false)->assertSee('id="product-search"',false)->assertSee('id="complete-now" name="complete_now" value="0"',false)->assertSee('Generated automatically on save')->assertSee('Nothing is added to stock yet.')->assertSee('Approve')->assertSee('Cancel');$service=app(ManufacturingService::class);
  $order=$service->createDraft(['product_id'=>$output->id,'bill_of_material_id'=>$bom->id,'location_id'=>$location->id,'quantity'=>20,'manufactured_at'=>today()->toDateString(),'expires_at'=>today()->addMonths(6)->toDateString(),'selling_price'=>50,'expenses'=>[['name'=>'Labour','amount'=>30]]],$user->id);
  $this->assertSame('draft',$order->status);$this->assertMatchesRegularExpression('/^MO-\d{7}$/',$order->number);$this->assertEquals(50,$order->components[0]->quantity);$this->assertEquals(100,$raw[0]->locations()->find($location->id)->pivot->opening_quantity);
  $service->confirm($order,$user->id);$service->start($order->fresh(),$user->id);$order=$service->complete($order->fresh(),[],$user->id);
  $this->assertSame('completed',$order->status);$this->assertMatchesRegularExpression('/^LOT-\d{8}-\d{6}$/',$order->outputLot->lot_number);$this->assertEquals($order->id,$order->outputLot->manufacturingOrder->id);$this->assertEquals(50,$raw[0]->locations()->find($location->id)->pivot->opening_quantity);$this->assertEquals(0,$raw[1]->locations()->find($location->id)->pivot->opening_quantity);$this->assertEquals(20,$order->outputLot->movements()->where('location_id',$location->id)->sum('quantity_delta'));
  $this->get('/products/'.$output->id.'/lots')->assertOk()->assertSee('Source')->assertSee('Manufacturing '.$order->number);
  $this->get('/manufacturing/orders/'.$order->id)->assertOk()->assertSee('Print Receipt')->assertSee('Back to Manufacturing Orders')->assertSee(route('manufacturing.index'),false)->assertSee('MANUFACTURING RECEIPT')->assertSee('TOTAL PRODUCTION COST')->assertSee($order->outputLot->lot_number);
 }
 public function test_direct_material_rows_create_a_draft_without_a_separate_recipe():void{
  [$user,$location,$raw,$output]=$this->fixtures();
  $candidate=Product::create(['name'=>'Direct Output','code'=>'DIRECT-OUT','unit_id'=>$output->unit_id,'is_active'=>true,'manage_stock'=>true,'is_raw_material'=>false,'is_manufacturable'=>false,'track_lots'=>false,'enable_serial'=>false,'product_type'=>'single','purchase_price'=>0,'selling_price'=>80]);$candidate->locations()->attach($location->id,['opening_quantity'=>0]);
  $this->post('/manufacturing',['product_id'=>$candidate->id,'location_id'=>$location->id,'quantity'=>20,'manufactured_at'=>today()->toDateString(),'expires_at'=>today()->addYear()->toDateString(),'selling_price'=>999,'components'=>[['product_id'=>$raw[0]->id,'source_lot_number'=>'MANUAL-RICE-01','quantity_per_unit'=>2.5],['product_id'=>$raw[1]->id,'quantity_per_unit'=>1]],'expenses'=>[['name'=>'Labour','amount'=>30]]])->assertSessionHasNoErrors();
  $order=\App\Models\ManufacturingOrder::where('product_id',$candidate->id)->firstOrFail();
  $this->assertNull($order->bill_of_material_id);$this->assertSame('draft',$order->status);$this->assertNull($order->expires_at);$this->assertEquals(80,$order->selling_price);$this->assertEquals(50,$order->components[0]->quantity);$this->assertSame('MANUAL-RICE-01',$order->components[0]->source_lot_reference);$this->assertEquals(20,$order->components[1]->quantity);$this->assertEquals(270,$order->total_cost);$this->assertTrue($candidate->fresh()->is_manufacturable);$this->assertTrue($candidate->fresh()->track_lots);
 }
 public function test_wizard_step_three_completes_stock_and_creates_a_traceable_lot():void{
  [$user,$location,$raw,$output]=$this->fixtures();
  $this->post('/manufacturing',['complete_now'=>1,'product_id'=>$output->id,'location_id'=>$location->id,'quantity'=>10,'manufactured_at'=>today()->toDateString(),'selling_price'=>40,'components'=>[['product_id'=>$raw[0]->id,'quantity_per_unit'=>2.5],['product_id'=>$raw[1]->id,'quantity_per_unit'=>1]],'expenses'=>[['name'=>'','amount'=>'']]])->assertSessionHasNoErrors();
  $order=\App\Models\ManufacturingOrder::latest('id')->firstOrFail();
  $this->assertSame('completed',$order->status);$this->assertNotNull($order->output_lot_id);$this->assertEquals(75,$raw[0]->locations()->find($location->id)->pivot->opening_quantity);$this->assertEquals(10,$raw[1]->locations()->find($location->id)->pivot->opening_quantity);$this->assertEquals(10,$order->outputLot->movements()->where('location_id',$location->id)->sum('quantity_delta'));$this->assertSame($order->id,$order->outputLot->manufacturingOrder->id);
 }
 public function test_insufficient_component_stock_rolls_back_completion():void{
  [$user,$location,$raw,$output,$bom]=$this->fixtures();$service=app(ManufacturingService::class);$order=$service->createDraft(['product_id'=>$output->id,'bill_of_material_id'=>$bom->id,'location_id'=>$location->id,'quantity'=>30,'manufactured_at'=>today()->toDateString(),'selling_price'=>50],$user->id);$service->confirm($order,$user->id);$service->start($order->fresh(),$user->id);
  try{$service->complete($order->fresh(),[],$user->id);$this->fail('Expected stock validation');}catch(ValidationException){}$this->assertSame('in_progress',$order->fresh()->status);$this->assertNull($order->fresh()->output_lot_id);$this->assertEquals(100,$raw[0]->locations()->find($location->id)->pivot->opening_quantity);
 }
 public function test_bom_listing_and_invalid_status_transition_are_guarded():void{
  [$user,$location,$raw,$output,$bom]=$this->fixtures();$this->get('/manufacturing/create')->assertOk()->assertSee('Product materials');$this->get('/manufacturing/boms')->assertRedirect('/manufacturing/create');$order=app(ManufacturingService::class)->createDraft(['product_id'=>$output->id,'bill_of_material_id'=>$bom->id,'location_id'=>$location->id,'quantity'=>10,'manufactured_at'=>today()->toDateString(),'selling_price'=>50],$user->id);$this->expectException(ValidationException::class);app(ManufacturingService::class)->start($order,$user->id);
 }
 public function test_open_order_can_be_cancelled_with_audit_without_stock_change():void{
  [$user,$location,$raw,$output,$bom]=$this->fixtures();$service=app(ManufacturingService::class);$order=$service->createDraft(['product_id'=>$output->id,'bill_of_material_id'=>$bom->id,'location_id'=>$location->id,'quantity'=>10,'manufactured_at'=>today()->toDateString(),'selling_price'=>50],$user->id);$service->confirm($order,$user->id);$cancelled=$service->cancel($order->fresh(),'Customer production request was withdrawn.',$user->id);$this->assertSame('cancelled',$cancelled->status);$this->assertSame($user->id,$cancelled->cancelled_by);$this->assertNotNull($cancelled->cancelled_at);$this->assertEquals(100,$raw[0]->locations()->find($location->id)->pivot->opening_quantity);$this->assertDatabaseCount('inventory_transactions',0);
  $this->expectException(ValidationException::class);$service->start($cancelled,$user->id);
 }
 public function test_serial_tracked_output_auto_generates_linked_available_serials():void{
  [$user,$location,$raw,$output,$bom]=$this->fixtures();$output->update(['enable_serial'=>true]);$service=app(ManufacturingService::class);$order=$service->createDraft(['product_id'=>$output->id,'bill_of_material_id'=>$bom->id,'location_id'=>$location->id,'quantity'=>2,'manufactured_at'=>today()->toDateString(),'selling_price'=>50],$user->id);$service->confirm($order,$user->id);$service->start($order->fresh(),$user->id);$completed=$service->complete($order->fresh(),[],$user->id,['serial_mode'=>'auto','serial_prefix'=>'PACK-TEST']);
  $serials=$completed->outputSerials()->orderBy('id')->get();$this->assertSame(['PACK-TEST-000001','PACK-TEST-000002'],$serials->pluck('serial_number')->all());$this->assertTrue($serials->every(fn($serial)=>$serial->status==='available'&&(int)$serial->location_id===$location->id&&(int)$serial->product_lot_id===$completed->output_lot_id));
 }
 public function test_manual_serial_duplicate_rolls_back_entire_completion():void{
  [$user,$location,$raw,$output,$bom]=$this->fixtures();$output->update(['enable_serial'=>true]);ProductSerialNumber::create(['product_id'=>$output->id,'location_id'=>$location->id,'serial_number'=>'EXISTING-001']);$service=app(ManufacturingService::class);$order=$service->createDraft(['product_id'=>$output->id,'bill_of_material_id'=>$bom->id,'location_id'=>$location->id,'quantity'=>2,'manufactured_at'=>today()->toDateString(),'selling_price'=>50],$user->id);$service->confirm($order,$user->id);$service->start($order->fresh(),$user->id);
  try{$service->complete($order->fresh(),[],$user->id,['serial_mode'=>'manual','manual_serials'=>"NEW-001\nEXISTING-001"]);$this->fail('Expected duplicate serial validation');}catch(ValidationException){}$this->assertSame('in_progress',$order->fresh()->status);$this->assertNull($order->fresh()->output_lot_id);$this->assertDatabaseCount('product_lots',0);$this->assertEquals(100,$raw[0]->locations()->find($location->id)->pivot->opening_quantity);
 }
 public function test_only_marked_single_or_variable_materials_are_used_and_selected_variant_stock_is_consumed():void{
  [$user,$location,$raw,$output]=$this->fixtures();$unit=$output->unit;
  $variable=Product::create(['name'=>'Phone Screen','code'=>'SCREEN','unit_id'=>$unit->id,'is_active'=>true,'manage_stock'=>true,'is_raw_material'=>true,'is_manufacturable'=>false,'track_lots'=>false,'enable_serial'=>false,'product_type'=>'variable','purchase_price'=>1,'selling_price'=>1]);
  $black=$variable->variants()->create(['sku'=>'SCREEN-BLK','purchase_price'=>12,'selling_price'=>18]);$white=$variable->variants()->create(['sku'=>'SCREEN-WHT','purchase_price'=>13,'selling_price'=>19]);
  $black->locationStocks()->create(['location_id'=>$location->id,'opening_quantity'=>10]);$white->locationStocks()->create(['location_id'=>$location->id,'opening_quantity'=>8]);
  $combo=Product::create(['name'=>'Forbidden Combo','code'=>'COMBO-RAW','unit_id'=>$unit->id,'is_active'=>true,'manage_stock'=>true,'is_raw_material'=>true,'is_manufacturable'=>false,'track_lots'=>false,'enable_serial'=>false,'product_type'=>'combo','purchase_price'=>1,'selling_price'=>1]);
  $notMarked=Product::create(['name'=>'Unmarked Part','code'=>'UNMARKED','unit_id'=>$unit->id,'is_active'=>true,'manage_stock'=>true,'is_raw_material'=>false,'is_manufacturable'=>false,'track_lots'=>false,'enable_serial'=>false,'product_type'=>'single','purchase_price'=>1,'selling_price'=>1]);
  $response=$this->get('/manufacturing/create?recipe=new')->assertOk()->assertSee('Phone Screen')->assertDontSee('Forbidden Combo');$this->assertFalse($response->viewData('materials')->contains('id',$notMarked->id));
  $bom=BillOfMaterial::create(['code'=>'BOM-PHONE-VARIANT','product_id'=>$output->id,'output_quantity'=>1,'is_active'=>true,'created_by'=>$user->id]);$bom->items()->create(['product_id'=>$variable->id,'product_variant_id'=>$black->id,'quantity'=>2,'position'=>0]);
  $service=app(ManufacturingService::class);$order=$service->createDraft(['product_id'=>$output->id,'bill_of_material_id'=>$bom->id,'location_id'=>$location->id,'quantity'=>2,'manufactured_at'=>today()->toDateString(),'selling_price'=>50],$user->id);
  $this->assertSame($black->id,$order->components()->first()->product_variant_id);$this->assertEquals(48,$order->material_cost);
  $service->confirm($order,$user->id);$service->start($order->fresh(),$user->id);$service->complete($order->fresh(),[],$user->id);
  $this->assertEquals(6,$black->locationStocks()->where('location_id',$location->id)->value('opening_quantity'));$this->assertEquals(8,$white->locationStocks()->where('location_id',$location->id)->value('opening_quantity'));
 }
 public function test_product_form_hides_internal_lot_flags_and_recipe_enables_output_automatically():void{
  [$user,$location,$raw,$output]=$this->fixtures();
  $this->get('/products/create')->assertOk()->assertSee('name="is_raw_material"',false)->assertDontSee('name="track_lots"',false)->assertDontSee('name="is_manufacturable"',false)->assertSee('Manufacturing and lot setup is automatic.');
  $candidate=Product::create(['name'=>'New Phone','code'=>'PHONE-NEW','unit_id'=>$output->unit_id,'is_active'=>true,'manage_stock'=>true,'is_raw_material'=>false,'is_manufacturable'=>false,'track_lots'=>false,'enable_serial'=>true,'product_type'=>'single','purchase_price'=>0,'selling_price'=>500]);
  $this->get('/manufacturing/create')->assertOk()->assertSee('New Phone')->assertSee('Only products assigned to the selected warehouse appear here.')->assertSee('Source lot')->assertSee('Select material first')->assertSee('Enter lot/reference manually')->assertDontSee('name="expires_at"',false);
  $this->get('/manufacturing/create?recipe=new')->assertOk()->assertSee('New Phone');
  $this->post('/manufacturing/boms',['code'=>'BOM-PHONE-NEW','product_id'=>$candidate->id,'output_quantity'=>1,'is_active'=>1,'items'=>[['product_id'=>$raw[0]->id,'quantity'=>2]]])->assertSessionHasNoErrors();
  $candidate->refresh();$this->assertTrue($candidate->is_manufacturable);$this->assertTrue($candidate->track_lots);$this->assertDatabaseHas('bills_of_materials',['code'=>'BOM-PHONE-NEW','product_id'=>$candidate->id]);
 }
 public function test_step_two_receives_positive_raw_material_lots_for_the_selected_warehouse():void{
  [$user,$location,$raw]=$this->fixtures();$raw[0]->update(['track_lots'=>true]);
  $lot=app(\App\Services\ProductLotService::class)->receive($raw[0],['location_id'=>$location->id,'quantity'=>25,'unit_cost'=>4,'selling_price'=>4],$user->id);
  $response=$this->get('/manufacturing/create')->assertOk()->assertSee('material-lot-select',false)->assertSee($lot->lot_number);
  $row=$response->viewData('lotData')->firstWhere('lot_number',$lot->lot_number);
  $this->assertNotNull($row);$this->assertSame($raw[0]->id,(int)$row->product_id);$this->assertSame($location->id,(int)$row->location_id);$this->assertEquals(25,$row->available);
 }
}
