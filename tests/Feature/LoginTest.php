<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        return $app;
    }

    public function test_database_username_and_password_log_in_the_matching_user(): void
    {
        $user = User::factory()->create(['username' => 'warehouse', 'password' => 'correct-password']);

        Livewire::test('auth.login')
            ->set('username', 'warehouse')
            ->set('password', 'correct-password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_username_cannot_use_another_accounts_password(): void
    {
        User::factory()->create(['username' => 'user', 'email' => 'user@nexuserp.com', 'password' => 'correct-password']);

        Livewire::test('auth.login')
            ->set('username', 'unknown')
            ->set('password', 'correct-password')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_old_hardcoded_password_cannot_bypass_database_password(): void
    {
        User::factory()->create(['username' => 'user', 'email' => 'user@nexuserp.com', 'password' => '123456']);

        Livewire::test('auth.login')
            ->set('username', 'user')
            ->set('password', '1234')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_does_not_create_accounts(): void
    {
        Livewire::test('auth.login')
            ->set('username', 'user')
            ->set('password', '1234')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_login_uses_database_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        Livewire::test('auth.login')
            ->set('username', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }
}
