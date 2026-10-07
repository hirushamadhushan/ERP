<?php
namespace Tests\Feature;

use App\Models\{Contact, DeliveryDriver, DeliveryVehicle, DeliveryTransfer, Location, Product, Unit, User, Role};
use App\Services\{DeliveryConsignmentService, DeliveryFleetService, DeliveryStockService, ProductLotService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        return $app;
    }
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') throw new \RuntimeException('Memory tests only');
        $this->artisan('migrate', ['--force'=>true])->assertExitCode(0);
    }
    private function fixtures(bool $lots = false): array
    {
        $user = User::factory()->create(['all_locations'=>true]);
        $this->actingAs($user);
        $warehouse = Location::create(['name'=>'Warehouse','code'=>'WH','is_active'=>true]);
        $unit = Unit::create(['name'=>'Pieces','short_name'=>'Pc','allow_decimal'=>false]);
        $product = Product::create(['name'=>'Test stock','code'=>'STOCK','unit_id'=>$unit->id,'is_active'=>true,'manage_stock'=>true,'track_lots'=>$lots,'product_type'=>'single','purchase_price'=>10,'selling_price'=>15]);
        $product->locations()->attach($warehouse->id,['opening_quantity'=>$lots ? 0 : 20]);
        $fleet = app(DeliveryFleetService::class);
        $vehicle = $fleet->saveVehicle(new DeliveryVehicle,['number'=>'CAB-1234','name'=>'Van','is_active'=>true]);
        $fleet->saveDriver(new DeliveryDriver,['name'=>'Driver','license_number'=>'LIC-TEST-001','license_expires_at'=>now()->addYear()->toDateString(),'is_active'=>true,'vehicle_id'=>$vehicle->id],$user->id);
        $data=['request_key'=>(string)Str::uuid(),'vehicle_id'=>$vehicle->id,'warehouse_id'=>$warehouse->id,'direction'=>'loading','lines'=>[['product_id'=>$product->id,'quantity'=>5]]];
        return [$user,$warehouse,$product,$vehicle,$data];
    }
    public function test_round_trip_and_repeated_submit_conserve_stock(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $service=app(DeliveryStockService::class);
        $first=$service->transfer($data,$user->id);
        $this->assertSame($first->id,$service->transfer($data,$user->id)->id);
        $this->assertEquals(15,$product->locations()->find($warehouse->id)->pivot->opening_quantity);
        $this->assertEquals(5,$product->locations()->find($vehicle->stores()->first()->id)->pivot->opening_quantity);
        $data['request_key']=(string)Str::uuid();$data['direction']='unloading';
        $service->transfer($data,$user->id);
        $this->assertEquals(20,$product->locations()->find($warehouse->id)->pivot->opening_quantity);
        $this->assertEquals(0,$product->locations()->find($vehicle->stores()->first()->id)->pivot->opening_quantity);
        foreach(['/delivery/vehicles','/delivery/drivers','/delivery/store?vehicle_id='.$vehicle->id,'/delivery/transfer','/delivery/vehicles/'.$vehicle->id.'/edit','/delivery/drivers/1/edit','/delivery/transfers/'.$first->id] as $url) $this->get($url)->assertOk();
        $user->update(['all_locations'=>false]);
        $this->get('/delivery/transfers/'.$first->id)->assertForbidden();
    }
    public function test_invalid_second_line_rolls_back_entire_transfer(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $data['lines'][]=['product_id'=>$product->id,'quantity'=>100];
        try { app(DeliveryStockService::class)->transfer($data,$user->id); $this->fail('Expected insufficient stock'); } catch(ValidationException $e) {}
        $this->assertDatabaseCount('delivery_transfers',0);
        $this->assertEquals(20,$product->locations()->find($warehouse->id)->pivot->opening_quantity);
    }
    public function test_lot_transfer_preserves_identity_and_total(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures(true);
        $lot=app(ProductLotService::class)->receive($product,['location_id'=>$warehouse->id,'quantity'=>20,'unit_cost'=>10,'selling_price'=>15],$user->id);
        $this->assertMatchesRegularExpression('/^LOT-\d{8}-\d{6}$/', $lot->lot_number);
        $this->get('/delivery/stock-options?vehicle_id='.$vehicle->id.'&warehouse_id='.$warehouse->id.'&direction=loading&q='.$lot->lot_number)
            ->assertOk()->assertJsonFragment(['lot_id' => $lot->id, 'lot_number' => $lot->lot_number]);
        $data['lines'][0]['lot_id']=$lot->id;
        app(DeliveryStockService::class)->transfer($data,$user->id);
        $this->assertEquals(20,$lot->movements()->sum('quantity_delta'));
        $this->assertEquals(15,$lot->movements()->where('location_id',$warehouse->id)->sum('quantity_delta'));
        $this->assertEquals(5,$lot->movements()->where('location_id',$vehicle->stores()->first()->id)->sum('quantity_delta'));
        $nextLot=app(ProductLotService::class)->receive($product,['location_id'=>$warehouse->id,'quantity'=>1,'unit_cost'=>11,'selling_price'=>16],$user->id);
        $this->assertSame($lot->id + 1, $nextLot->id);
        $this->assertStringEndsWith(str_pad((string) $nextLot->id, 6, '0', STR_PAD_LEFT), $nextLot->lot_number);
    }
    public function test_permissions_and_required_vehicle_fields(): void
    {
        [$user]=$this->fixtures();
        $this->post('/delivery/vehicles',['name'=>'Missing number','is_active'=>1])->assertSessionHasErrors('number');
        $role=Role::create(['name'=>'Restricted']);$role->syncPermissions(['delivery.view']);$user->update(['role_id'=>$role->id]);$user->unsetRelation('assignedRole');
        $this->get('/delivery/vehicles')->assertOk();
        $this->get('/delivery/consignments')->assertOk();
        $this->post('/delivery/vehicles',[])->assertForbidden();
        $this->post('/delivery/transfer',[])->assertForbidden();
        $this->post('/delivery/consignments',[])->assertForbidden();
    }

    public function test_vehicle_unloading_requires_its_own_permission(): void
    {
        [$user, $warehouse, $product, $vehicle, $data] = $this->fixtures();
        $role = Role::create(['name' => 'Loading operator']);
        $role->syncPermissions(['delivery.view', 'delivery.transfer']);
        $user->update(['role_id' => $role->id]);
        $user->unsetRelation('assignedRole');

        $this->get('/delivery/loading?vehicle_id='.$vehicle->id)->assertOk();
        $this->get('/delivery/unloading?vehicle_id='.$vehicle->id)->assertForbidden();
        $this->get('/delivery/transfer?direction=unloading&vehicle_id='.$vehicle->id)->assertForbidden();
        $this->get('/delivery/stock-options?direction=unloading&vehicle_id='.$vehicle->id)->assertForbidden();
        $data['direction'] = 'unloading';
        $this->post('/delivery/transfer', $data)->assertForbidden();

        $role->syncPermissions(['delivery.view', 'delivery.transfer', 'delivery.unload']);
        $user->unsetRelation('assignedRole');
        $this->get('/delivery/unloading?vehicle_id='.$vehicle->id)->assertOk();
    }

    public function test_serial_transfer_moves_only_selected_serial_and_cannot_repeat_it(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $product->update(['enable_serial'=>true]);
        $serial=\App\Models\ProductSerialNumber::create(['product_id'=>$product->id,'location_id'=>$warehouse->id,'serial_number'=>'SER-001']);
        $this->get('/delivery/stock-options?vehicle_id='.$vehicle->id.'&warehouse_id='.$warehouse->id.'&direction=loading&q=SER-001')
            ->assertOk()->assertJsonFragment(['serial_id' => $serial->id, 'serial_number' => 'SER-001']);
        $data['lines'][0]['quantity']=1;$data['lines'][0]['serial_ids']=[$serial->id];
        $response=$this->post('/delivery/transfer',$data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals($vehicle->stores()->first()->id,$serial->fresh()->location_id);
        $data['request_key']=(string)Str::uuid();
        $this->post('/delivery/transfer',$data)->assertSessionHasErrors('transfer');
        $this->assertDatabaseCount('delivery_transfers',1);
    }

    public function test_unloaded_serial_loading_record_cannot_create_a_delivery(): void
    {
        [$user, $warehouse, $product, $vehicle, $data] = $this->fixtures();
        $product->update(['enable_serial' => true]);
        $serial = \App\Models\ProductSerialNumber::create([
            'product_id' => $product->id, 'location_id' => $warehouse->id,
            'serial_number' => 'SER-STALE-001',
        ]);
        $data['lines'][0] = ['product_id' => $product->id, 'quantity' => 1, 'serial_ids' => [$serial->id]];
        $stockService = app(DeliveryStockService::class);
        $loading = $stockService->transfer($data, $user->id);

        $data['request_key'] = (string) Str::uuid();
        $data['direction'] = 'unloading';
        $stockService->transfer($data, $user->id);

        $customer = Contact::create(['type' => 'customer', 'contact_id' => 'C-STALE', 'name' => 'Stale Customer', 'mobile' => '0770000000']);
        $this->assertFalse($loading->fresh()->hasStockAvailableForConsignment());
        $this->get('/delivery/consignments')->assertOk()->assertDontSee('SER-STALE-001');
        $this->expectException(ValidationException::class);
        app(DeliveryConsignmentService::class)->create([
            'loading_transfer_id' => $loading->id, 'customer_id' => $customer->id,
            'delivery_address' => 'Test Address',
        ], $user->id);
    }

    public function test_assignment_conflicts_and_inactive_loading_are_rejected(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $this->assertDatabaseHas('delivery_vehicle_assignment_history',['driver_id'=>1,'vehicle_id'=>$vehicle->id,'action'=>'assigned']);
        $this->post('/delivery/drivers',['name'=>'Second driver','is_active'=>1,'vehicle_id'=>$vehicle->id])->assertSessionHasErrors('vehicle_id');
        $this->assertDatabaseCount('delivery_drivers',1);
        $vehicle->update(['is_active'=>false]);
        $this->post('/delivery/transfer',$data)->assertSessionHasErrors('transfer');
        $this->assertDatabaseCount('delivery_transfers',0);
        $user->update(['all_locations'=>false]);
        $this->post('/delivery/transfer',$data)->assertForbidden();
    }

    public function test_departure_requires_a_valid_driver_license_and_vehicle_documents(): void
    {
        [$user, $warehouse, $product, $vehicle, $data] = $this->fixtures();
        $transfer = app(DeliveryStockService::class)->transfer($data, $user->id);
        $customer = Contact::create(['type' => 'customer', 'contact_id' => 'C-COMPLY', 'name' => 'Compliance Customer', 'mobile' => '0770000000']);
        $delivery = app(DeliveryConsignmentService::class)->create([
            'loading_transfer_id' => $transfer->id,
            'customer_id' => $customer->id,
            'delivery_address' => 'Compliance Address',
        ], $user->id);

        $transfer->driver->update(['license_expires_at' => now()->subDay()->toDateString()]);

        $this->post('/delivery/consignments/'.$delivery->id.'/depart')
            ->assertSessionHasErrors('status');
        $this->assertSame('loaded', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->departed_at);
    }

    public function test_delivery_outcome_consumes_vehicle_stock(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $transfer=app(DeliveryStockService::class)->transfer($data,$user->id);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'C-TEST','name'=>'Test Customer','mobile'=>'0770000000']);
        $service=app(DeliveryConsignmentService::class);
        $delivery=$service->create(['loading_transfer_id'=>$transfer->id,'customer_id'=>$customer->id,'delivery_address'=>'Test Address'],$user->id);
        $this->assertDatabaseCount('delivery_consignments',1);
        $this->get('/delivery/consignments/'.$delivery->id)->assertOk()->assertSee('Delivery Receipt & Proof', false)->assertSee('Mark in transit');
        $this->get('/delivery/consignments')->assertOk();
        $this->get('/delivery/consignments/create')->assertOk();
        $this->get('/delivery/consignments/'.$delivery->id)->assertOk();
        $service->depart($delivery,$user->id);
        $this->get('/delivery/consignments/'.$delivery->id)->assertOk()->assertSee('Delivery Receipt & Proof', false)->assertSee('Mark arrived at customer');
        $this->post('/delivery/consignments/'.$delivery->id.'/arrive')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($delivery->fresh()->arrived_at);
        $service->complete($delivery,['receiver_name'=>'Receiver','lines'=>[['id'=>$delivery->lines->first()->id,'delivered'=>3,'damaged'=>1,'missing'=>1,'remarks'=>'One damaged; one missing']]],$user->id);
        $this->assertSame('partial',$delivery->fresh()->status);
        $this->assertEquals(0,$product->locations()->find($vehicle->stores()->first()->id)->pivot->opening_quantity);
        $this->assertEquals(15,$product->locations()->find($warehouse->id)->pivot->opening_quantity);
        $this->assertDatabaseHas('delivery_consignment_lines',['consignment_id'=>$delivery->id,'delivered_quantity'=>3,'missing_quantity'=>1,'remarks'=>'One damaged; one missing']);
        $this->get('/delivery/consignments/'.$delivery->id)->assertOk()->assertSee('One damaged; one missing')->assertSee('Damaged Qty');
        $this->assertDatabaseCount('delivery_consignment_events',4);
        Storage::fake('local');
        $png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==';
        $this->post('/delivery/consignments/'.$delivery->id.'/proofs',['kind'=>'signature','signature_data'=>'data:image/png;base64,'.$png])->assertSessionHasNoErrors()->assertRedirect();
        $proof=$delivery->proofs()->firstOrFail();
        Storage::disk('local')->assertExists($proof->path);
        $this->get('/delivery/consignments/'.$delivery->id.'/proofs/'.$proof->id)->assertOk();
        $this->expectException(ValidationException::class);
        $service->complete($delivery,['receiver_name'=>'Receiver','lines'=>[['id'=>$delivery->lines->first()->id,'delivered'=>5,'damaged'=>0,'missing'=>0]]],$user->id);
    }

    public function test_invalid_delivery_quantities_do_not_change_vehicle_stock(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $transfer=app(DeliveryStockService::class)->transfer($data,$user->id);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'C-TEST','name'=>'Test Customer','mobile'=>'0770000000']);
        $service=app(DeliveryConsignmentService::class);
        $delivery=$service->create(['loading_transfer_id'=>$transfer->id,'customer_id'=>$customer->id,'delivery_address'=>'Test Address'],$user->id);
        $service->depart($delivery,$user->id);
        $service->arrive($delivery,$user->id);
        try {
            $service->complete($delivery,['receiver_name'=>'Receiver','lines'=>[['id'=>$delivery->lines->first()->id,'delivered'=>6,'damaged'=>0,'missing'=>0]]],$user->id);
            $this->fail('Expected quantity validation');
        } catch (ValidationException $e) {}
        $this->assertSame('arrived',$delivery->fresh()->status);
        $this->assertEquals(5,$product->locations()->find($vehicle->stores()->first()->id)->pivot->opening_quantity);
    }

    public function test_active_delivery_reserves_vehicle_for_its_current_job(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $stockService=app(DeliveryStockService::class);
        $transfer=$stockService->transfer($data,$user->id);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'C-RESERVE','name'=>'Reserved Customer','mobile'=>'0770000000']);
        $deliveryService=app(DeliveryConsignmentService::class);
        $delivery=$deliveryService->create(['loading_transfer_id'=>$transfer->id,'customer_id'=>$customer->id,'delivery_address'=>'Reserved Address'],$user->id);

        $blocked=$data;
        $blocked['request_key']=(string) Str::uuid();
        $blocked['direction']='unloading';
        try {
            $stockService->transfer($blocked,$user->id);
            $this->fail('Expected active-delivery reservation validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transfer',$exception->errors());
        }

        $this->get('/delivery/store?vehicle_id='.$vehicle->id)->assertOk()->assertSee('Continue '.$delivery->number)->assertDontSee('↑ Loading');
        $this->assertDatabaseCount('delivery_transfers',1);
        $this->assertEquals(5,$product->locations()->find($vehicle->stores()->first()->id)->pivot->opening_quantity);
    }

    public function test_delivery_dashboard_and_loading_unloading_screens_use_real_routes(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $this->get('/delivery/consignments')->assertOk()->assertSee('Delivery Dashboard')->assertSee('Ready to create')->assertDontSee('RT001');
        $this->get('/delivery/loading?vehicle_id='.$vehicle->id)->assertOk()->assertSee('Loading Operation')->assertSee('Scan Item')->assertSee('Add Item')->assertSee('Confirm Loading')->assertDontSee('Save Draft')->assertDontSee('Total Weight')->assertDontSee('Mineral Water 500ml');
        $this->get('/delivery/stock-options?vehicle_id='.$vehicle->id.'&warehouse_id='.$warehouse->id.'&direction=loading&q=STOCK')
            ->assertOk()->assertJsonFragment(['sku' => 'STOCK', 'scan_codes' => ['STOCK']]);
        app(DeliveryStockService::class)->transfer($data, $user->id);
        $this->get('/delivery/unloading?vehicle_id='.$vehicle->id)->assertOk()->assertSee('Unloading Operation')->assertSee('Select Vehicle Stock')->assertSee('Scan Item')->assertSee('Add Item')->assertSee('Confirm Unloading')->assertDontSee('Receiver Signature')->assertDontSee('Submit POD');
        $this->get('/delivery/stock-options?vehicle_id='.$vehicle->id.'&direction=unloading&q=STOCK')
            ->assertOk()->assertJsonFragment(['sku' => 'STOCK']);
        $this->get('/delivery/transfer?direction=unloading&vehicle_id='.$vehicle->id)->assertOk()->assertSee('Unloading Operation')->assertSee('Confirm Unloading');
        $this->get('/delivery/consignments?date_from=2026-01-01&date_to=2026-12-31&vehicle_id='.$vehicle->id)->assertOk();
    }

    public function test_delivery_consumes_only_delivered_lot_stock(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures(true);
        $lot=app(ProductLotService::class)->receive($product,['location_id'=>$warehouse->id,'quantity'=>20,'unit_cost'=>10,'selling_price'=>15],$user->id);
        $data['lines'][0]['lot_id']=$lot->id;
        $transfer=app(DeliveryStockService::class)->transfer($data,$user->id);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'C-LOT','name'=>'Lot Customer','mobile'=>'0770000000']);
        $service=app(DeliveryConsignmentService::class);
        $delivery=$service->create(['loading_transfer_id'=>$transfer->id,'customer_id'=>$customer->id,'delivery_address'=>'Test Address'],$user->id);
        $service->depart($delivery,$user->id);
        $service->arrive($delivery,$user->id);
        $service->complete($delivery,['receiver_name'=>'Receiver','lines'=>[['id'=>$delivery->lines->first()->id,'delivered'=>3,'damaged'=>0,'missing'=>2]]],$user->id);
        $this->assertEquals(0,$lot->movements()->where('location_id',$vehicle->stores()->first()->id)->sum('quantity_delta'));
        $this->assertEquals(15,$lot->movements()->where('location_id',$warehouse->id)->sum('quantity_delta'));
    }

    public function test_delivered_serial_leaves_available_vehicle_stock(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $product->update(['enable_serial'=>true]);
        $serial=\App\Models\ProductSerialNumber::create(['product_id'=>$product->id,'location_id'=>$warehouse->id,'serial_number'=>'SER-DLV-001']);
        $data['lines'][0]['quantity']=1;
        $data['lines'][0]['serial_ids']=[$serial->id];
        $transfer=app(DeliveryStockService::class)->transfer($data,$user->id);
        $customer=Contact::create(['type'=>'customer','contact_id'=>'C-SERIAL','name'=>'Serial Customer','mobile'=>'0770000000']);
        $service=app(DeliveryConsignmentService::class);
        $delivery=$service->create(['loading_transfer_id'=>$transfer->id,'customer_id'=>$customer->id,'delivery_address'=>'Test Address'],$user->id);
        $service->depart($delivery,$user->id);
        $service->arrive($delivery,$user->id);
        $service->complete($delivery,['receiver_name'=>'Receiver','lines'=>[['id'=>$delivery->lines->first()->id,'delivered'=>1,'damaged'=>0,'missing'=>0]]],$user->id);
        $this->assertSame('sold',$serial->fresh()->status);
        $this->assertSame('delivered',$delivery->fresh()->status);
        $this->assertEmpty(app(\App\Services\DeliveryInventory::class)->options($vehicle->stores()->first()->id,'SER-DLV-001'));
    }
}
