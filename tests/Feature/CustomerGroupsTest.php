<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerGroupsTest extends TestCase
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
            throw new \RuntimeException('Only an in-memory database is allowed.');
        }
        $this->artisan('migrate', ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_09_11_120000_create_contacts_table.php',
        ], '--force' => true])->assertExitCode(0);
        Contact::create(['name' => 'Existing customer', 'mobile' => '0771234567', 'type' => 'customer', 'contact_id' => 'C1', 'customer_group' => 'Legacy']);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_11_130000_create_customer_groups_table.php', '--force' => true])->assertExitCode(0);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_11_140000_add_selling_price_group_to_customer_groups.php', '--force' => true])->assertExitCode(0);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['name' => 'Wholesale', 'calculation_type' => 'percentage', 'calculation_percentage' => '10.25'], $overrides);
    }

    public function test_selling_price_mode_is_saved_and_switching_modes_clears_inactive_values(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/customer-groups', $this->payload(['calculation_type' => 'selling_price_group']))->assertSessionHasErrors('selling_price_group');
        $this->post('/customer-groups', $this->payload(['calculation_type' => 'selling_price_group', 'selling_price_group' => 'Trade prices']))->assertSessionHasNoErrors();
        $group = CustomerGroup::where('name', 'Wholesale')->firstOrFail();
        $this->assertSame('selling_price_group', $group->calculation_type);
        $this->assertSame('0.00', $group->calculation_percentage);
        $this->get('/customer-groups')->assertOk()->assertSee('Trade prices')->assertSee('percentage-tooltip');
        $this->put(route('contacts.groups.update', $group), $this->payload(['calculation_percentage' => -10]))->assertSessionHasNoErrors();
        $this->assertNull($group->fresh()->selling_price_group);
        $this->assertSame('-10.00', $group->fresh()->calculation_percentage);
    }

    public function test_guests_are_redirected_and_existing_groups_are_preserved(): void
    {
        $this->get('/customer-groups')->assertRedirect('/login');
        $this->post('/customer-groups', $this->payload())->assertRedirect('/login');
        $this->assertDatabaseHas('customer_groups', ['name' => 'Legacy', 'calculation_percentage' => 0]);
        $this->assertDatabaseHas('contacts', ['customer_group' => 'Legacy']);
    }

    public function test_create_group_makes_it_available_in_customer_form(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/customer-groups', $this->payload())->assertSessionHasNoErrors()->assertRedirect('/customer-groups');
        $this->assertDatabaseHas('customer_groups', ['name' => 'Wholesale', 'calculation_percentage' => 10.25]);
        $this->get('/customer-groups')->assertOk()->assertSee('Wholesale')->assertSee('Selling Price Group');
        $this->get('/contacts/customer')->assertOk()->assertSee('value="Wholesale"', false);
        $this->post('/contacts', ['type' => 'customer', 'entity_type' => 'individual', 'name' => 'New Customer', 'mobile' => '0771111111', 'status' => 'active', 'customer_group' => 'Wholesale'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contacts', ['name' => 'New Customer', 'customer_group' => 'Wholesale']);
    }

    public function test_rename_updates_customers_and_in_use_groups_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $group = CustomerGroup::where('name', 'Legacy')->firstOrFail();
        $this->put(route('contacts.groups.update', $group), $this->payload(['name' => 'Renamed']))->assertSessionHasNoErrors()->assertRedirect('/customer-groups');
        $this->assertDatabaseHas('contacts', ['customer_group' => 'Renamed']);
        $this->from('/customer-groups')->delete(route('contacts.groups.destroy', $group))->assertSessionHas('group_error')->assertRedirect('/customer-groups');
        $this->assertDatabaseHas('customer_groups', ['id' => $group->id]);
    }

    public function test_unused_group_can_be_deleted_without_deleting_customers(): void
    {
        $this->actingAs(User::factory()->create());
        $group = CustomerGroup::create($this->payload());
        $this->delete(route('contacts.groups.destroy', $group))->assertRedirect('/customer-groups');
        $this->assertDatabaseMissing('customer_groups', ['id' => $group->id]);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_invalid_percentage_duplicate_name_and_unknown_group_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/customer-groups', $this->payload(['name' => 'Legacy', 'calculation_percentage' => '101']))->assertSessionHasErrors(['name', 'calculation_percentage']);
        $this->post('/contacts', ['type' => 'customer', 'entity_type' => 'individual', 'name' => 'New Customer', 'mobile' => '0771111111', 'status' => 'active', 'customer_group' => 'Unknown'])->assertSessionHasErrors('customer_group');
    }
}
