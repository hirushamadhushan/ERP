<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
            'password' => 'secure-password', 'role' => 'Stock Supervisor', 'status' => 'active',
        ])->assertOk()->assertJsonPath('status', 'success')->json('user');

        $this->getJson('/users/'.$user['id'])->assertOk()->assertJsonMissing(['password']);
        $this->putJson('/users/'.$user['id'], [
            'username' => 'stock.user', 'name' => 'Updated Stock User', 'email' => 'stock.user@example.test',
            'role' => 'Stock Supervisor', 'status' => 'offline',
        ])->assertOk()->assertJsonPath('user.name', 'Updated Stock User');
        $this->postJson('/users', [
            'username' => 'invalid.role', 'name' => 'Invalid Role', 'email' => 'invalid@example.test',
            'password' => 'secure-password', 'role' => 'Missing Role', 'status' => 'active',
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
            'password' => 'secure-password', 'role' => 'Staff', 'status' => 'active',
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
