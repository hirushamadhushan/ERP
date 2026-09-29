<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    public function test_guests_cannot_access_user_or_role_management(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->get('/roles')->assertRedirect('/login');
    }

    public function test_role_and_user_crud_work_with_database_roles(): void
    {
        $this->actingAs(User::factory()->create());

        $role = $this->postJson('/roles', ['name' => 'Stock Clerk', 'description' => 'Manages stock'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->json('role');
        $this->putJson('/roles/'.$role['id'], ['name' => 'Stock Supervisor', 'description' => 'Supervises stock', 'permissions' => ['products.view']])
            ->assertOk()
            ->assertJsonPath('role.name', 'Stock Supervisor');

        $user = $this->postJson('/users', [
            'username' => 'stock.user', 'name' => 'Stock User', 'email' => 'stock.user@example.test',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password', 'role' => 'Stock Supervisor', 'status' => 'active',
        ])->assertOk()->assertJsonPath('status', 'success')->json('user');

        $this->getJson('/users/'.$user['id'])->assertOk()->assertJsonMissing(['password']);
        $this->putJson('/users/'.$user['id'], [
            'username' => 'stock.user', 'name' => 'Updated Stock User', 'email' => 'stock.user@example.test',
            'role' => 'Stock Supervisor', 'status' => 'offline',
        ])->assertOk()->assertJsonPath('user.name', 'Updated Stock User');
        $this->postJson('/users', [
            'username' => 'invalid.role', 'name' => 'Invalid Role', 'email' => 'invalid@example.test',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password', 'role' => 'Missing Role', 'status' => 'active',
        ])->assertJsonValidationErrors('role');

        $this->deleteJson('/users/'.$user['id'])->assertOk();
        $this->deleteJson('/roles/'.$role['id'])->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user['id']]);
        $this->assertDatabaseMissing('roles', ['id' => $role['id']]);
    }

    public function test_admin_user_and_role_are_protected(): void
    {
        $adminRole = Role::create(['name' => 'Admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs(User::factory()->create());

        $this->getJson('/users/'.$admin->id.'/edit')->assertForbidden();
        $this->deleteJson('/users/'.$admin->id)->assertForbidden();
        $this->deleteJson('/roles/'.$adminRole->id)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
    }

    public function test_user_email_must_be_lowercase_for_create_and_update(): void
    {
        Role::create(['name' => 'Staff']);
        $this->actingAs(User::factory()->create());

        $payload = [
            'username' => 'case.test', 'name' => 'Case Test', 'email' => 'Case.Test@example.com',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password', 'role' => 'Staff', 'status' => 'active',
        ];
        $this->postJson('/users', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Email address must contain lowercase letters only.');
        $this->assertDatabaseMissing('users', ['email' => 'Case.Test@example.com']);

        $user = User::factory()->create(['email' => 'case.test@example.com', 'role' => 'Staff']);
        $this->putJson('/users/'.$user->id, array_replace($payload, ['username' => $user->username]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertSame('case.test@example.com', $user->fresh()->email);
    }

    public function test_full_user_access_profile_and_security_data_are_saved_atomically(): void
    {
        $this->actingAs(User::factory()->create());
        $role = Role::create(['name' => 'Cashier']);
        $location = Location::create(['name' => 'Main Outlet', 'code' => 'MAIN']);
        $contact = Contact::create([
            'type' => 'customer', 'contact_id' => 'CUS-001', 'name' => 'Test Customer',
            'mobile' => '0771234567', 'status' => 'active',
        ]);

        $this->get('/users/create')->assertOk()
            ->assertSee('First Name:*')
            ->assertSee('Roles and Permissions')
            ->assertSee('Sales Commission Percentage')
            ->assertSee('More Informations')
            ->assertSee('Main Outlet')
            ->assertSee('Test Customer');
        $this->get('/users')->assertOk()
            ->assertSee('openFullUserBtn')
            ->assertSee('fullUserModal')
            ->assertDontSee('id="addUserModal"', false);

        $response = $this->postJson('/users', [
            'prefix' => 'Ms', 'first_name' => 'Nimali', 'last_name' => 'Perera',
            'email' => 'nimali@example.test', 'allow_login' => 1, 'username' => 'nimali.perera',
            'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!',
            'role_id' => $role->id, 'status' => 'active', 'all_locations' => 0,
            'location_ids' => [$location->id], 'commission_percent' => '2.50',
            'max_sales_discount_percent' => '10.00', 'restrict_contacts' => 1,
            'contact_ids' => [$contact->id], 'mobile' => '0710000000',
            'account_number' => '001-TEST',
        ])->assertOk()->assertJsonPath('status', 'success');

        $user = User::findOrFail($response->json('user.id'));
        $this->assertSame('Ms Nimali Perera', $user->name);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertNotSame('SecurePass123!', $user->password);
        $this->assertSame([$location->id], $user->locations()->pluck('locations.id')->all());
        $this->assertSame([$contact->id], $user->selectedContacts()->pluck('contacts.id')->all());
        $this->assertSame('001-TEST', $user->profile->account_number);
    }

    public function test_login_disabled_user_needs_no_credentials_and_gets_no_specific_access_by_default(): void
    {
        $this->actingAs(User::factory()->create());
        $role = Role::create(['name' => 'Commission Agent']);

        $id = $this->postJson('/users', [
            'first_name' => 'External Agent', 'email' => 'agent@example.test',
            'allow_login' => 0, 'role_id' => $role->id, 'status' => 'active',
            'all_locations' => 1, 'commission_percent' => 5,
            'max_sales_discount_percent' => 0, 'restrict_contacts' => 0,
        ])->assertOk()->json('user.id');

        $user = User::findOrFail($id);
        $this->assertFalse($user->allow_login);
        $this->assertNull($user->username);
        $this->assertTrue($user->locations()->doesntExist());
        $this->assertTrue($user->selectedContacts()->doesntExist());
    }

    public function test_login_disabled_account_is_rejected_even_with_a_valid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.test',
            'allow_login' => false,
            'status' => 'active',
            'password' => Hash::make('ValidPassword123!'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'ValidPassword123!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_specific_access_and_percentage_rules_are_validated(): void
    {
        $this->actingAs(User::factory()->create());
        $role = Role::create(['name' => 'Sales']);

        $this->postJson('/users', [
            'first_name' => 'Invalid User', 'email' => 'invalid.user@example.test',
            'allow_login' => 0, 'role_id' => $role->id, 'status' => 'active',
            'all_locations' => 0, 'commission_percent' => 101,
            'max_sales_discount_percent' => -1, 'restrict_contacts' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'location_ids', 'contact_ids', 'commission_percent', 'max_sales_discount_percent',
        ]);
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
    }
}
