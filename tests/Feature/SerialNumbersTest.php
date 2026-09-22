<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductSerialNumber;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class SerialNumbersTest extends TestCase
{
    public function test_generator_can_preview_and_save_inside_the_modal(): void
    {
        $data = $this->settings() + ['embedded' => 1];
        $this->get('/products/serial-numbers')->assertOk()->assertSee('serial-generator-dialog');
        $this->get('/products/serial-numbers/create?embedded=1')->assertOk()->assertSee('name="embedded"', false)->assertDontSee('Back to serial numbers');
        $this->post('/products/serial-numbers/preview', $data)->assertOk()->assertSee('name="embedded"', false);
        $this->post('/products/serial-numbers', ['token' => session('serial_preview.token'), 'embedded' => 1])->assertOk()->assertSee('serial-generator-saved');
        $this->post('/products/serial-numbers', ['token' => 'expired', 'embedded' => 1])->assertRedirect('/products/serial-numbers/create?embedded=1')->assertSessionHasErrors();
    }

    public function test_report_includes_all_filtered_pages_and_escapes_values(): void
    {
        $ids = $this->setupReferences();
        for ($i = 1; $i <= 51; $i++) {
            ProductSerialNumber::create($ids + ['serial_number' => 'REPORT-'.$i]);
        }
        $this->get('/products/serial-numbers/report?status=available')->assertOk()->assertSee('REPORT-1<', false)->assertSee('REPORT-51<', false);
        $this->get('/products/serial-numbers/report?status=sold')->assertOk()->assertDontSee('REPORT-1');
    }

    public function test_empty_malformed_and_formula_workbooks_are_rejected(): void
    {
        $this->setupReferences();
        foreach ([$this->workbook([]), UploadedFile::fake()->createWithContent('broken.xlsx', 'not a workbook'), $this->workbook([[1, 1, '=1+1']])] as $file) {
            $this->post('/products/serial-numbers/import', ['file' => $file])->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('product_serial_numbers', 0);
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
            throw new \RuntimeException('Only in-memory tests allowed.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function setupReferences(): array
    {
        $this->actingAs(User::factory()->create());

        $product = Product::create(['name' => 'Pump', 'code' => 'P1', 'enable_serial' => true, 'manage_stock' => true]);
        $location = Location::create(['name' => 'Warehouse', 'code' => 'W1']);
        $product->locations()->attach($location);

        return ['product_id' => $product->id, 'location_id' => $location->id];
    }

    private function settings(): array
    {
        return $this->setupReferences() + ['prefix' => 'ABC', 'separator' => '-', 'middle_fix' => '', 'post_fix' => '', 'start_number' => 1, 'count' => 2, 'padding' => 4, 'paper_width' => 76, 'label_width' => 25, 'label_height' => 12, 'position' => 'right', 'x_offset' => 0, 'y_offset' => 0, 'gap' => 2, 'copies' => 1, 'barcode_format' => 'CODE128', 'barcode_height' => 6.5, 'barcode_margin' => 0, 'show_text' => 0];
    }

    public function test_authentication_and_reference_registration(): void
    {
        $this->get('/products/serial-numbers')->assertRedirect('/login');
        $this->post('/products/serial-numbers/import')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->post('/products/serial-numbers/references', ['kind' => 'product', 'name' => 'Pump', 'code' => 'P'])->assertRedirect('/products/create');
        $this->assertDatabaseCount('products', 0);
        $this->post('/products/serial-numbers/references', ['kind' => 'location', 'name' => 'Warehouse', 'code' => 'W'])->assertSessionHasNoErrors();
        $this->post('/products/serial-numbers/references', ['kind' => 'location', 'name' => 'Warehouse', 'code' => 'W'])->assertSessionHasErrors('code');
        $this->get('/products/serial-numbers')->assertOk()->assertSee('Warehouse');
        $this->get('/products/serial-numbers/create')->assertOk();
    }

    public function test_preview_save_and_duplicate_replay_protection(): void
    {
        $data = $this->settings();
        $this->post('/products/serial-numbers/preview', $data)->assertOk()->assertSee('ABC-0001');
        $this->assertDatabaseCount('product_serial_numbers', 0);
        $token = session('serial_preview.token');
        $this->post('/products/serial-numbers', ['token' => $token])->assertOk()->assertSee('Serial numbers saved');
        $this->assertDatabaseCount('product_serial_numbers', 2);
        $this->post('/products/serial-numbers', ['token' => $token])->assertSessionHasErrors();
        $this->post('/products/serial-numbers/preview', $data)->assertSessionHasErrors();
        $this->assertDatabaseCount('product_serial_numbers', 2);
    }

    public function test_invalid_generator_and_label_dimensions(): void
    {
        $data = $this->settings();
        foreach ([['count' => 1001], ['product_id' => 999], ['label_width' => 100], ['barcode_height' => 50], ['barcode_format' => 'CODE39', 'prefix' => 'abc']] as $bad) {
            $this->post('/products/serial-numbers/preview', array_replace($data, $bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('product_serial_numbers', 0);
    }

    private function workbook(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'serial-test-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['product_id', 'location_id', 'serial_number']));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        $content = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('serials.xlsx', $content);
    }

    public function test_excel_import_preserves_text_and_rejects_invalid_batches(): void
    {
        $ids = $this->setupReferences();
        $this->post('/products/serial-numbers/import', ['file' => $this->workbook([[$ids['product_id'], $ids['location_id'], '0001234567890123456789']])])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_serial_numbers', ['serial_number' => '0001234567890123456789']);
        foreach ([[[1, 1, 'new'], [999, 1, 'bad']], [[1, 1, 'dup'], [1, 1, 'DUP']], [[1, 1, 123]], [[1, 1, '0001234567890123456789']]] as $rows) {
            $this->post('/products/serial-numbers/import', ['file' => $this->workbook($rows)])->assertSessionHasErrors();
            $this->assertDatabaseCount('product_serial_numbers', 1);
        }
        $this->get('/products/serial-numbers/template')->assertOk()->assertDownload('serial-numbers-template.xlsx');
    }

    public function test_csv_alias_filters_and_sold_deletion_protection(): void
    {
        $ids = $this->setupReferences();
        $file = UploadedFile::fake()->createWithContent('serials.csv', "product_id,location_id,serial_id\n1,1,0001\n1,1,0002\n");
        $this->post('/products/serial-numbers/import', ['file' => $file])->assertSessionHasNoErrors();
        DB::table('product_serial_numbers')->where('serial_number', '0002')->update(['status' => 'sold', 'sold_transaction_id' => 123]);
        $this->get('/products/serial-numbers?status=available')->assertOk()
            ->assertSee('data-auto-filter', false)->assertDontSee('>Filter<', false)
            ->assertSee('0001')->assertDontSee('0002');
        $this->delete('/products/serial-numbers', ['ids' => ProductSerialNumber::pluck('id')->all()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_serial_numbers', 1);
        $this->assertDatabaseHas('product_serial_numbers', ['serial_number' => '0002']);
    }
}
