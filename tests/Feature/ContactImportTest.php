<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use App\Services\ContactCsvImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactImportTest extends TestCase
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
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Only in-memory tests are allowed.');
        }
        $this->artisan('migrate', ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_11_120000_create_contacts_table.php',
            'database/migrations/2026_09_11_130000_create_customer_groups_table.php',
            'database/migrations/2026_09_11_140000_add_selling_price_group_to_customer_groups.php',
            'database/migrations/2026_09_11_150000_add_date_of_birth_to_contacts.php',
        ], '--force' => true])->assertExitCode(0);
    }

    private function row(array $overrides = []): array
    {
        return array_replace(array_fill_keys(ContactCsvImport::COLUMNS, ''), ['Contact type' => '1', 'First Name' => 'Import Person', 'Mobile' => '0771234567'], $overrides);
    }

    private function csv(array $rows, ?array $header = null): UploadedFile
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $header ?? ContactCsvImport::COLUMNS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_values($row), ',', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('contacts.csv', $content);
    }

    public function test_import_and_template_require_authentication(): void
    {
        $this->get('/contacts/import')->assertRedirect('/login');
        $this->get('/contacts/import/template')->assertRedirect('/login');
        $this->post('/contacts/import', ['file' => $this->csv([$this->row()])])->assertRedirect('/login');
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_page_and_download_match_the_27_column_template(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/contacts/import')->assertOk()->assertSee('File To Import')->assertSee('Custom Field 4')->assertSee('3 = Both');
        $response = $this->get('/contacts/import/template')->assertOk()->assertDownload('contacts-template.csv');
        $header = str_getcsv(substr($response->streamedContent(), 3), ',', '"', '');
        $header[count($header) - 1] = trim(end($header));
        $this->assertSame(ContactCsvImport::COLUMNS, $header);
    }

    public function test_customers_suppliers_and_both_import_without_losing_details(): void
    {
        $this->actingAs(User::factory()->create());
        $rows = [
            $this->row(['Contact ID' => 'IMPORT-C1', 'Prefix' => 'Ms', 'First Name' => 'Nimali', 'Middle name' => 'K', 'Last Name' => 'Silva', 'Date of birth' => '1990-01-15', 'Custom Field 1' => '0', 'Custom Field 4' => 'Text, with comma']),
            $this->row(['Contact type' => '2', 'Contact ID' => 'IMPORT-S1', 'First Name' => 'Supplier Only', 'Business Name' => 'Supplier Co', 'Pay term' => '30', 'Pay term period' => 'days']),
            $this->row(['Contact type' => '3', 'Contact ID' => 'IMPORT-B1', 'First Name' => 'Shared Contact', 'Business Name' => 'Shared Co', 'Opening Balance' => '150.50', 'Pay term' => '2', 'Pay term period' => 'months']),
        ];
        $this->post('/contacts/import', ['file' => $this->csv($rows)])->assertSessionHasNoErrors()->assertRedirect('/contacts/import')->assertSessionHas('success');
        $this->assertDatabaseCount('contacts', 3);
        $customer = Contact::where('contact_id', 'IMPORT-C1')->firstOrFail();
        $this->assertSame('Ms Nimali K Silva', $customer->name);
        $this->assertSame('0771234567', $customer->mobile);
        $this->assertSame('1990-01-15', $customer->date_of_birth->format('Y-m-d'));
        $this->assertSame(['0', null, null, 'Text, with comma'], $customer->custom_fields);
        $this->get('/contacts/customer')->assertOk()->assertSee('Shared Contact')->assertDontSee('Supplier Only');
        $this->get('/contacts/supplier')->assertOk()->assertSee('Shared Contact')->assertSee('Supplier Only')->assertDontSee('Ms Nimali');
        $this->get('/contacts/commission')->assertOk()->assertDontSee('Shared Contact');
    }

    public function test_invalid_row_prevents_entire_import_and_reports_record_number(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->from('/contacts/import')->post('/contacts/import', ['file' => $this->csv([$this->row(), $this->row(['Mobile' => '', 'Date of birth' => 'bad-date'])])]);
        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('Row 3:', session('errors')->first('file'));
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_duplicate_ids_never_overwrite_existing_contacts(): void
    {
        $this->actingAs(User::factory()->create());
        Contact::create(['name' => 'Existing', 'type' => 'customer', 'mobile' => '0771111111', 'contact_id' => 'EXISTING']);
        $this->post('/contacts/import', ['file' => $this->csv([$this->row(['Contact ID' => 'NEW']), $this->row(['Contact ID' => 'EXISTING'])])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseHas('contacts', ['contact_id' => 'EXISTING', 'name' => 'Existing']);
        $this->post('/contacts/import', ['file' => $this->csv([$this->row(['Contact ID' => 'DUP']), $this->row(['Contact ID' => 'dup'])])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_blank_ids_are_generated_and_supplier_requirements_are_enforced(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/contacts/import', ['file' => $this->csv([$this->row(['Contact type' => '2'])])])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('contacts', 0);
        $this->post('/contacts/import', ['file' => $this->csv([$this->row(), $this->row()])])->assertSessionHasNoErrors();
        $this->assertSame(2, Contact::distinct()->count('contact_id'));
    }

    public function test_empty_wrong_header_wrong_column_count_and_oversized_file_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/contacts/import', ['file' => $this->csv([])])->assertSessionHasErrors('file');
        $this->post('/contacts/import', ['file' => $this->csv([$this->row()], ['Wrong header'])])->assertSessionHasErrors('file');
        $this->post('/contacts/import', ['file' => $this->csv([['1', 'Short row']])])->assertSessionHasErrors('file');
        $this->post('/contacts/import', ['file' => UploadedFile::fake()->create('large.csv', 2049, 'text/csv')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('contacts', 0);
    }
}
