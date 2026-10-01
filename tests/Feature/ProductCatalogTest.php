<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    public function test_catalogue_and_stock_report_only_render_the_requested_server_page(): void
    {
        $this->data();
        for ($number = 1; $number <= 51; $number++) {
            Product::create([
                'name' => 'Paged Product '.$number,
                'code' => sprintf('PAGE-%03d', $number),
                'product_type' => 'single',
                'unit_id' => 1,
                'purchase_price' => 10,
                'selling_price' => 15,
                'selling_price_tax_type' => 'exclusive',
            ]);
        }

        $firstPage = $this->get('/products')->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('id="product-server-pagination" data-async-region', false)
            ->assertSee('paging:false', false);
        $this->assertCount(50, $firstPage->viewData('products'));
        $this->assertSame('PAGE-051', $firstPage->viewData('products')->first()->code);

        $secondPage = $this->get('/products?page=2')->assertOk();
        $this->assertCount(1, $secondPage->viewData('products'));
        $this->assertSame('PAGE-001', $secondPage->viewData('products')->first()->code);

        $searched = $this->get('/products?product_search=PAGE-001')->assertOk();
        $this->assertSame(['PAGE-001'], $searched->viewData('products')->pluck('code')->all());
    }

    public function test_deep_category_selection_is_saved_and_restored(): void
    {
        $data = $this->data();
        $root = \App\Models\Category::create(['name' => 'Hardware', 'category_type' => 'product']);
        $leaf = $root;
        for ($level = 1; $level <= 5; $level++) {
            $leaf = \App\Models\Category::create(['name' => 'Depth '.$level, 'category_type' => 'product', 'parent_id' => $leaf->id]);
        }
        $data['category_id'] = $root->id;
        $data['subcategory_id'] = $leaf->id;
        $this->post('/products', $data)->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertEquals($root->id, $product->category_id);
        $this->assertEquals($leaf->id, $product->subcategory_id);
        $this->get('/products/'.$product->id.'/edit')->assertOk()->assertSee('Depth 5')->assertSee('data-path=', false);
        $this->get('/products?category_id='.$root->id)->assertOk()->assertSee('PUMP-001');
        $other = \App\Models\Category::create(['name' => 'Other', 'category_type' => 'product']);
        $data['category_id'] = $other->id;
        $this->put('/products/'.$product->id, $data)->assertSessionHasErrors('subcategory_id');
        $this->assertEquals($leaf->id, $product->fresh()->selected_category_id);
    }

    public function test_variable_and_combo_products_are_saved_with_their_details(): void
    {
        $base = $this->data();
        $this->post('/products', array_replace($base, ['name' => 'Base Item', 'sku' => 'BASE-001', 'enable_serial' => 0]))->assertSessionHasNoErrors();
        $baseProduct = Product::firstOrFail();
        $template = \App\Models\VariationTemplate::create(['name' => 'Sizes', 'values' => ['S', 'M', 'L']]);

        $variable = array_replace($base, [
            'name' => 'Variable Shirt', 'sku' => 'SHIRT-001', 'product_type' => 'variable', 'enable_serial' => 0,
            'variation_template_id' => $template->id,
            'variants' => [
                ['value' => 'S', 'sku' => 'SHIRT-S', 'purchase_price' => 100, 'selling_price' => 150],
                ['value' => 'M', 'sku' => '', 'purchase_price' => 110, 'selling_price' => 165],
                ['value' => 'L', 'sku' => 'SHIRT-L', 'purchase_price' => 120, 'selling_price' => 180],
            ],
        ]);
        $this->post('/products', $variable)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_variants', 3);
        $this->assertSame('M', $baseProduct->newQuery()->where('code', 'SHIRT-001')->firstOrFail()->variants()->where('sku', 'SHIRT-001-M')->firstOrFail()->value);
        $variableProduct = Product::where('code', 'SHIRT-001')->firstOrFail();
        $this->put('/products/'.$variableProduct->id, $variable)->assertSessionHasNoErrors();
        $this->assertSame('M', $variableProduct->variants()->where('sku', 'SHIRT-001-M')->firstOrFail()->value);
        $variableProduct->refresh()->load(['variants', 'locations']);
        $quantities = $variableProduct->variants->mapWithKeys(fn ($variant) => [
            $variant->id => $variableProduct->locations->mapWithKeys(fn ($location) => [$location->id => 5])->all(),
        ])->all();
        $this->post('/products/'.$variableProduct->id.'/opening-stock', ['quantities' => $quantities])->assertSessionHasNoErrors();
        $this->get('/products?product_id='.$variableProduct->id)->assertOk()->assertSee('15 Pc');

        $combo = array_replace($base, [
            'name' => 'Starter Bundle', 'sku' => 'BUNDLE-001', 'product_type' => 'combo', 'enable_serial' => 0,
            'combo_items' => [['product_id' => $baseProduct->id, 'quantity' => 2]],
        ]);
        $this->post('/products', $combo)->assertSessionHasNoErrors();
        $comboProduct = Product::where('code', 'BUNDLE-001')->firstOrFail();
        $this->assertDatabaseHas('combo_product_items', ['combo_product_id' => $comboProduct->id, 'item_product_id' => $baseProduct->id, 'quantity' => 2]);
        $this->assertEquals(200, $comboProduct->purchase_price);
        $this->assertEquals(250, $comboProduct->selling_price);
        $this->get('/products/create')->assertOk()->assertSee('Variable')->assertSee('Combo')->assertSee('Sizes')->assertSee('Enter product name / SKU / Scan bar code')->assertSee('Net Total Amount');
    }

    public function test_product_list_filters_stock_report_and_safe_delete(): void
    {
        $data = $this->data();
        $data['enable_serial'] = 0;
        $this->post('/products', $data)->assertSessionHasNoErrors();
        $this->post('/products/1/opening-stock', ['quantities' => [1 => 8]])->assertSessionHasNoErrors();

        $this->get('/products?unit_id=1&stock_status=managed')
            ->assertOk()
            ->assertSee('data-auto-filter', false)
            ->assertDontSee('Apply Filters')
            ->assertSee('List Products')
            ->assertSee('product-table-host', false)
            ->assertSee('StickyDataTables.install(productsTable)', false)
            ->assertSee('StickyDataTables.install(stockTable)', false)
            ->assertSee('Water Pump')
            ->assertSee('Stock Report')
            ->assertSee('8 Pc')
            ->assertSee('Current Stock Value')
            ->assertSee('Potential')
            ->assertSee('Custom Field 4')
            ->assertSee('Rs 800.00')
            ->assertSee('Rs 1,000.00')
            ->assertSee('Rs 200.00');
        $this->get('/products?unit_id=999')->assertSessionHasErrors('unit_id');

        $this->delete('/products/1')
            ->assertSessionHasNoErrors()
            ->assertRedirect('/products');
        $this->assertDatabaseMissing('products', ['id' => 1]);
    }

    public function test_product_tax_filter_returns_every_matching_product(): void
    {
        $first = $this->data();
        $first['sku'] = 'FILTER-TAX-ONE';
        $first['tax_rate'] = 8;
        $this->post('/products', $first)->assertSessionHasNoErrors();

        $second = $first;
        $second['sku'] = 'FILTER-TAX-TWO';
        $second['name'] = 'Second tax product';
        $second['tax_rate'] = 8;
        $this->post('/products', $second)->assertSessionHasNoErrors();

        $other = $first;
        $other['sku'] = 'FILTER-TAX-OTHER';
        $other['name'] = 'Other tax product';
        $other['tax_rate'] = 12;
        $this->post('/products', $other)->assertSessionHasNoErrors();

        $otherProduct = Product::where('code', 'FILTER-TAX-OTHER')->firstOrFail();
        $this->get('/products?tax_rate=8')
            ->assertOk()
            ->assertSee('FILTER-TAX-ONE')
            ->assertSee('FILTER-TAX-TWO')
            ->assertDontSee('data-product-id="'.$otherProduct->id.'"', false);
    }

    public function test_product_filter_options_do_not_shrink_with_location_results(): void
    {
        $first = $this->data();
        $first['sku'] = 'MAIN-WH-ITEM';
        $this->post('/products', $first)->assertSessionHasNoErrors();

        $otherLocation = Location::create(['name' => 'Outlet', 'code' => 'OUTLET']);
        $second = $first;
        $second['name'] = 'Outlet Item';
        $second['sku'] = 'OUTLET-ITEM';
        $second['location_ids'] = [$otherLocation->id];
        $this->post('/products', $second)->assertSessionHasNoErrors();

        $this->get('/products?location_id=1')
            ->assertOk()
            ->assertSee('MAIN-WH-ITEM')
            ->assertSee('Outlet Item')
            ->assertDontSee('data-product-id="2"', false);
    }

    public function test_ajax_save_returns_destination_and_validation_errors(): void
    {
        $data = $this->data();
        $this->postJson('/products', array_replace($data, ['name' => '']))->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('products', 0);
        $this->postJson('/products', $data)->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('redirect', route('products.catalog.index'));
        $this->assertDatabaseCount('products', 1);
    }

    public function test_excel_sku_import_and_reference_sheets(): void
    {
        $data = $this->data();
        $this->post('/products', $data)->assertSessionHasNoErrors();
        $path = tempnam(sys_get_temp_dir(), 'sku-import-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['product_sku', 'location_code', 'serial_number']));
        $writer->addRow(Row::fromValues(['PUMP-001', 'WH-01', '000000789']));
        $writer->close();
        $file = UploadedFile::fake()->createWithContent('sku.xlsx', file_get_contents($path));
        unlink($path);
        $this->post('/products/serial-numbers/import', ['file' => $file])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_serial_numbers', ['serial_number' => '000000789', 'product_id' => 1, 'location_id' => 1]);
        $response = $this->get('/products/serial-numbers/template')->assertOk();
        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[] = $sheet->getName();
            if ($sheet->getName() === 'Products') {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                $this->assertSame('PUMP-001', $rows[1][1]);
                $this->assertSame('WH-01', $rows[1][3]);
            }
        }
        $reader->close();
        $this->assertSame(['Serial Numbers', 'Products', 'Locations'], $sheets);
    }

    public function test_image_upload_and_unit_protection(): void
    {
        $data = $this->data();
        $data['image'] = UploadedFile::fake()->createWithContent('image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aJ1kAAAAASUVORK5CYII='));
        $this->post('/products', $data)->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertStringStartsWith('data:image/png;base64,', $product->image_path);
        $this->get('/products/1/files/image')->assertOk();
        $this->delete('/units/1')->assertSessionHas('unit_error');
        $this->put('/units/1', ['name' => 'Pieces', 'short_name' => 'Pc', 'allow_decimal' => 1])->assertSessionHasErrors('allow_decimal');
        $this->assertDatabaseHas('units', ['id' => 1, 'allow_decimal' => false]);
    }

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
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Memory tests only.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        Storage::fake('local');
    }

    private function data(): array
    {
        $this->actingAs(User::factory()->create());
        $unit = Unit::create(['name' => 'Pieces', 'short_name' => 'Pc', 'allow_decimal' => false]);
        $location = Location::create(['name' => 'Warehouse', 'code' => 'WH-01']);

        return ['name' => 'Water Pump', 'sku' => 'PUMP-001', 'unit_id' => $unit->id, 'brand_id' => null, 'category_id' => null, 'subcategory_id' => null, 'location_ids' => [$location->id], 'barcode_type' => 'CODE128', 'manage_stock' => 1, 'enable_serial' => 1, 'not_for_selling' => 0, 'product_type' => 'single', 'tax_rate' => 10, 'selling_price_tax_type' => 'exclusive', 'purchase_price' => 100, 'selling_price' => 125, 'margin' => 25, 'save_action' => 'save', 'description' => '<p><strong>Good</strong><img src=x onerror=alert(1)><script>bad()</script></p>', 'custom_fields' => ['one', 'two']];
    }

    public function test_product_save_manual_auto_sku_and_price_calculations(): void
    {
        $data = $this->data();
        $this->post('/products', $data)->assertSessionHasNoErrors()->assertRedirect('/products');
        $product = Product::firstOrFail();
        $this->assertSame('PUMP-001', $product->code);
        $this->assertEquals(110, $product->purchase_price_inc);
        $this->assertEquals(25, $product->margin);
        $this->assertSame('<p><strong>Good</strong></p>', $product->description);
        $this->assertCount(1, $product->locations);
        $this->get('/products')->assertOk()->assertSee('PUMP-001');
        $this->get('/products/'.$product->id.'/edit')->assertOk()->assertSee('Water Pump');
        $this->post('/products', array_replace($data, ['sku' => 'pump-001']))->assertSessionHasErrors('sku');
        $this->post('/products', array_replace($data, ['sku' => '', 'selling_price_tax_type' => 'inclusive', 'selling_price' => 137.5, 'save_action' => 'another']))->assertSessionHasNoErrors()->assertRedirect('/products/create');
        $auto = Product::latest('id')->first();
        $this->assertStringStartsWith('PRD-', $auto->code);
        $this->assertEquals(125, $auto->selling_price);
        $this->put('/products/'.$product->id, array_replace($data, ['sku' => '', 'name' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertSame('PUMP-001', $product->fresh()->code);
        foreach (['0', '0000123'] as $sku) {
            $this->post('/products', array_replace($data, ['sku' => $sku]))->assertSessionHasNoErrors();
            $this->assertSame($sku, Product::latest('id')->first()->code);
        }
    }

    public function test_sku_import_and_location_tracking_rules(): void
    {
        $data = $this->data();
        $this->post('/products', $data)->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $upload = fn ($text) => UploadedFile::fake()->createWithContent('serials.csv', $text);
        $this->post('/products/serial-numbers/import', ['file' => $upload("product_sku,location_code,serial_number\nPUMP-001,WH-01,000001\n")])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_serial_numbers', ['product_id' => $product->id, 'serial_number' => '000001']);
        Location::create(['name' => 'Other', 'code' => 'OTHER']);
        $this->post('/products/serial-numbers/import', ['file' => $upload("product_sku,location_code,serial_number\nPUMP-001,WH-01,000002\nPUMP-001,OTHER,000003\n")])->assertSessionHasErrors();
        $this->assertDatabaseCount('product_serial_numbers', 1);
        $this->put('/products/'.$product->id, array_replace($data, ['enable_serial' => 0]))->assertSessionHasErrors('enable_serial');
        $this->put('/products/'.$product->id, array_replace($data, ['location_ids' => [2]]))->assertSessionHasErrors('location_ids');
        $this->get('/products/serial-numbers/create?embedded=1')->assertOk()->assertSee('PUMP-001')->assertSee('data-locations="1"', false);
    }

    public function test_opening_stock_replaces_values_and_enforces_whole_units(): void
    {
        $data = $this->data();
        $data['enable_serial'] = 0;
        $data['save_action'] = 'opening';
        $this->post('/products', $data)->assertSessionHasNoErrors()->assertRedirect('/products/1/opening-stock');
        $this->post('/products/1/opening-stock', ['quantities' => [1 => 10]])->assertSessionHasNoErrors();
        $this->post('/products/1/opening-stock', ['quantities' => [1 => 5]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('location_product', ['product_id' => 1, 'opening_quantity' => 5]);
        $this->post('/products/1/opening-stock', ['quantities' => [1 => 1.5]])->assertSessionHasErrors();
        $this->put('/products/1', array_replace($data, ['enable_serial' => 1]))->assertSessionHasErrors();
        $this->post('/products/serial-numbers/import', ['file' => UploadedFile::fake()->createWithContent('s.csv', "product_id,location_id,serial_number\n1,1,ABC\n")])->assertSessionHasErrors();
    }

    public function test_validation_quick_references_and_uploads(): void
    {
        $this->get('/products/create')->assertRedirect('/login');
        $data = $this->data();
        $this->post('/products', array_replace($data, ['unit_id' => 999]))->assertSessionHasErrors('unit_id');
        $this->post('/products', array_replace($data, ['manage_stock' => 0]))->assertSessionHasErrors('enable_serial');
        $this->post('/products', array_replace($data, ['sku' => 'bad space']))->assertSessionHasErrors('sku');
        $this->postJson('/products/quick-reference', ['kind' => 'category', 'name' => 'Parent'])->assertCreated();
        $this->postJson('/products/quick-reference', ['kind' => 'category', 'name' => 'Child', 'parent_id' => 1])->assertCreated();
        $this->postJson('/products/quick-reference', ['kind' => 'brand', 'name' => 'Brand'])->assertCreated();
        $data['category_id'] = 1;
        $data['subcategory_id'] = 2;
        $data['brand_id'] = 1;
        $data['brochure'] = UploadedFile::fake()->createWithContent('info.csv', "a,b\n1,2");
        $this->post('/products', $data)->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        Storage::disk('local')->assertExists($product->brochure_path);
        $this->get('/products/'.$product->id.'/files/brochure')->assertOk();
        $this->get('/products/'.$product->id.'/files/invalid')->assertNotFound();
        $this->post('/products', array_replace($data, ['sku' => 'NEW', 'subcategory_id' => 1]))->assertSessionHasErrors('subcategory_id');
    }
}
