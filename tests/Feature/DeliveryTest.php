<?php
namespace Tests\Feature;

use App\Models\{DeliveryDriver, DeliveryVehicle, DeliveryTransfer, Location, Product, Unit, User, Role};
use App\Services\{DeliveryFleetService, DeliveryStockService, ProductLotService};
use Illuminate\Support\Facades\DB;
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
        $fleet->saveDriver(new DeliveryDriver,['name'=>'Driver','is_active'=>true,'vehicle_id'=>$vehicle->id],$user->id);
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
        $data['lines'][0]['lot_id']=$lot->id;
        app(DeliveryStockService::class)->transfer($data,$user->id);
        $this->assertEquals(20,$lot->movements()->sum('quantity_delta'));
        $this->assertEquals(15,$lot->movements()->where('location_id',$warehouse->id)->sum('quantity_delta'));
        $this->assertEquals(5,$lot->movements()->where('location_id',$vehicle->stores()->first()->id)->sum('quantity_delta'));
    }
    public function test_permissions_and_required_vehicle_fields(): void
    {
        [$user]=$this->fixtures();
        $this->post('/delivery/vehicles',['name'=>'Missing number','is_active'=>1])->assertSessionHasErrors('number');
        $role=Role::create(['name'=>'Restricted']);$role->syncPermissions(['delivery.view']);$user->update(['role_id'=>$role->id]);$user->unsetRelation('assignedRole');
        $this->get('/delivery/vehicles')->assertOk();
        $this->post('/delivery/vehicles',[])->assertForbidden();
        $this->post('/delivery/transfer',[])->assertForbidden();
    }

    public function test_serial_transfer_moves_only_selected_serial_and_cannot_repeat_it(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $product->update(['enable_serial'=>true]);
        $serial=\App\Models\ProductSerialNumber::create(['product_id'=>$product->id,'location_id'=>$warehouse->id,'serial_number'=>'SER-001']);
        $data['lines'][0]['quantity']=1;$data['lines'][0]['serial_ids']=[$serial->id];
        $response=$this->post('/delivery/transfer',$data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals($vehicle->stores()->first()->id,$serial->fresh()->location_id);
        $data['request_key']=(string)Str::uuid();
        $this->post('/delivery/transfer',$data)->assertSessionHasErrors('transfer');
        $this->assertDatabaseCount('delivery_transfers',1);
    }

    public function test_assignment_conflicts_and_inactive_loading_are_rejected(): void
    {
        [$user,$warehouse,$product,$vehicle,$data]=$this->fixtures();
        $this->post('/delivery/drivers',['name'=>'Second driver','is_active'=>1,'vehicle_id'=>$vehicle->id])->assertSessionHasErrors('vehicle_id');
        $this->assertDatabaseCount('delivery_drivers',1);
        $vehicle->update(['is_active'=>false]);
        $this->post('/delivery/transfer',$data)->assertSessionHasErrors('transfer');
        $this->assertDatabaseCount('delivery_transfers',0);
        $user->update(['all_locations'=>false]);
        $this->post('/delivery/transfer',$data)->assertForbidden();
    }
}
