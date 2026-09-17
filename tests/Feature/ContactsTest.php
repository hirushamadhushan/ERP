<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactsTest extends TestCase
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
        // Refuse to run migrations unless the connection is explicitly in memory.
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Contacts tests require an isolated in-memory database.');
        }
        $this->artisan('migrate', ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_11_120000_create_contacts_table.php',
            'database/migrations/2026_09_11_130000_create_customer_groups_table.php',
            'database/migrations/2026_09_11_140000_add_selling_price_group_to_customer_groups.php',
            'database/migrations/2026_09_11_150000_add_date_of_birth_to_contacts.php',
        ], '--force' => true])->assertExitCode(0);
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        CustomerGroup::firstOrCreate(['name' => 'Retail']);
        $this->actingAs($user);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'type' => 'customer', 'entity_type' => 'individual',
            'name' => 'Test contact', 'mobile' => '0771234567', 'status' => 'active',
        ], $overrides);
    }

    public function test_guests_cannot_access_or_create_contacts(): void
    {
        $this->get('/contacts/customer')->assertRedirect('/login');
        $this->post('/contacts', $this->payload())->assertRedirect('/login');
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_supplier_form_includes_and_saves_all_reference_fields(): void
    {
        $user = $this->signIn();
        $data = $this->payload([
            'type' => 'supplier', 'entity_type' => 'business', 'business_name' => 'Supply Company',
            'contact_id' => 'SUP-100', 'alternate_number' => '0772222222', 'landline' => '0112222222',
            'email' => 'supplier@example.com', 'assigned_to' => $user->id, 'tax_number' => 'TAX-100',
            'opening_balance' => '1500.50', 'pay_term' => 2, 'pay_term_unit' => 'months',
            'address_line_1' => '10 Main Road', 'address_line_2' => 'Unit 2', 'city' => 'Colombo',
            'state' => 'Western', 'country' => 'Sri Lanka', 'zip_code' => '00100',
            'shipping_address' => 'Warehouse entrance',
            'custom_fields' => array_map(fn ($i) => 'Value '.$i, range(1, 10)),
        ]);
        $page = $this->get('/contacts/supplier')->assertOk();
        foreach (array_keys($data) as $field) {
            if ($field !== 'custom_fields') {
                $page->assertSee('name="'.$field.'"', false);
            }
        }
        for ($i = 0; $i < 10; $i++) {
            $page->assertSee('name="custom_fields['.$i.']"', false);
        }
        $page->assertSee('More information')->assertSee('Months')->assertSee('Days');
        $this->post('/contacts', $data)->assertSessionHasNoErrors()->assertRedirect('/contacts/supplier');
        $contact = Contact::firstOrFail();
        foreach ($data as $field => $value) {
            $this->assertEquals($value, $contact->$field, $field);
        }
        $this->getJson(route('contacts.show', $contact))->assertOk()->assertJsonPath('custom_fields.9', 'Value 10');
    }

    public function test_all_three_pages_render_in_the_shared_layout_and_separate_records(): void
    {
        $this->signIn();
        foreach (Contact::TYPES as $type => $title) {
            Contact::create($this->payload(['type' => $type, 'name' => 'Only-'.$type, 'contact_id' => 'TEST-'.$type]));
        }
        foreach (Contact::TYPES as $type => $title) {
            $response = $this->get('/contacts/'.$type)->assertOk()->assertSee('Codeza ERP')->assertSee('Only-'.$type)->assertSee('contacts-table')->assertSee('StickyDataTables.install', false)->assertSee('sticky-table-host', false);
            foreach (array_diff(array_keys(Contact::TYPES), [$type]) as $other) {
                $response->assertDontSee('Only-'.$other);
            }
        }
    }

    public function test_customer_details_are_saved_with_generated_id_and_returned_for_editing(): void
    {
        $user = $this->signIn();
        $page = $this->get('/contacts/customer')->assertOk();
        foreach (['type', 'entity_type', 'contact_id', 'customer_group', 'mobile', 'alternate_number', 'landline', 'email', 'assigned_to', 'tax_number', 'opening_balance', 'pay_term', 'pay_term_unit', 'credit_limit', 'opening_due_cans', 'address_line_1', 'address_line_2', 'city', 'state', 'country', 'zip_code', 'shipping_address'] as $field) {
            $page->assertSee('name="'.$field.'"', false);
        }
        for ($i = 0; $i < 10; $i++) {
            $page->assertSee('name="custom_fields['.$i.']"', false);
        }
        $this->post('/contacts', $this->payload([
            'assigned_to' => $user->id, 'opening_balance' => '125.50',
            'credit_limit' => null, 'pay_term' => 30, 'pay_term_unit' => 'days',
            'custom_fields' => array_fill(0, 10, 'Notes'), 'address_line_1' => 'Main Street',
            'opening_due_cans' => 12,
            'shipping_address' => 'Warehouse', 'customer_group' => 'Retail',
        ]))->assertSessionHasNoErrors()->assertRedirect('/contacts/customer');
        $contact = Contact::firstOrFail();
        $this->assertStringStartsWith('C-', $contact->contact_id);
        $this->assertSame('125.50', $contact->opening_balance);
        $this->assertNull($contact->credit_limit);
        $this->assertSame(array_fill(0, 10, 'Notes'), $contact->custom_fields);
        $this->assertEquals(12, $contact->opening_due_cans);
        $this->assertSame('Retail', $contact->customer_group);
        $this->getJson(route('contacts.show', $contact))->assertOk()->assertJsonPath('shipping_address', 'Warehouse');
    }

    public function test_commission_rate_is_validated_and_persisted(): void
    {
        $this->signIn();
        $this->post('/contacts', $this->payload(['type' => 'commission', 'commission_percentage' => 101]))->assertSessionHasErrors('commission_percentage');
        $this->post('/contacts', $this->payload(['type' => 'commission', 'commission_percentage' => '12.50']))->assertRedirect('/contacts/commission');
        $this->assertSame('12.50', Contact::firstOrFail()->commission_percentage);
    }

    public function test_duplicate_ids_and_invalid_assignees_are_rejected(): void
    {
        $this->signIn();
        Contact::create($this->payload(['contact_id' => 'C100']));
        $this->post('/contacts', $this->payload(['contact_id' => 'C100', 'assigned_to' => 99999]))->assertSessionHasErrors(['contact_id', 'assigned_to']);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_edit_keeps_generated_id_and_delete_only_removes_target_contact(): void
    {
        $this->signIn();
        $contact = Contact::create($this->payload(['contact_id' => 'C100']));
        $other = Contact::create($this->payload(['contact_id' => 'C101']));
        $this->put(route('contacts.update', $contact), $this->payload(['contact_id' => '', 'name' => 'Changed']))->assertRedirect('/contacts/customer');
        $this->assertSame('Changed', $contact->fresh()->name);
        $this->assertSame('C100', $contact->fresh()->contact_id);
        $this->delete(route('contacts.destroy', $contact))->assertRedirect('/contacts/customer');
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('contacts', ['id' => $other->id]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_filters_use_recorded_balances_assignment_group_and_sale_dates(): void
    {
        $user = $this->signIn();
        $matching = Contact::create($this->payload(['contact_id' => 'C1', 'name' => 'Matching customer', 'opening_balance' => 100, 'customer_group' => 'Retail', 'assigned_to' => $user->id]));
        $matching->forceFill(['due_balance' => 50, 'last_sale_at' => now()->subDays(100)])->save();
        Contact::create($this->payload(['contact_id' => 'C2', 'name' => 'Other customer', 'status' => 'inactive']));
        $this->get('/contacts/customer?'.http_build_query(['status' => 'active', 'opening' => 1, 'due' => 1, 'customer_group' => 'Retail', 'assigned_to' => $user->id, 'no_sales' => 30]))
            ->assertOk()->assertSee('data-auto-filter', false)->assertDontSee('Apply filters')
            ->assertSee('Matching customer')->assertDontSee('Other customer');
    }

    public function test_contact_text_is_escaped_in_the_table(): void
    {
        $this->signIn();
        Contact::create($this->payload(['contact_id' => 'C1', 'name' => '<script>alert(1)</script>']));
        $this->get('/contacts/customer')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
