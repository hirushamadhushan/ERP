<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
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
            throw new \RuntimeException('Memory tests only.');
        }
        config(['app.debug' => true]);
        Route::middleware('web')->get('/_test/errors/{status}', function ($status) {
            abort((int) $status, 'secret technical details');
        });
        Route::middleware('web')->get('/_test/failure', function () {
            throw new \RuntimeException('SECRET connection string');
        });
        Route::middleware('web')->post('/_test/validation', function (Request $request) {
            $request->validate(['name' => 'required']);
        });
        Route::middleware('web')->get('/_test/conflict', function () {
            $previous = new \PDOException('SQL contains private data');
            $previous->errorInfo = ['23000', 19, 'private'];
            throw new QueryException('sqlite', 'insert private', [], $previous);
        });
    }

    public function test_errors_have_safe_html_and_json_with_correct_statuses(): void
    {
        foreach ([403, 404, 405, 413, 419, 429, 503] as $status) {
            $this->get('/_test/errors/'.$status)->assertStatus($status)->assertSee('Nexus ERP')->assertDontSee('secret technical details');
            $this->getJson('/_test/errors/'.$status)->assertStatus($status)->assertJsonStructure(['message', 'status'])->assertDontSee('secret technical details');
        }
    }

    public function test_unexpected_errors_hide_debug_details_and_supply_reference(): void
    {
        $this->getJson('/_test/failure')->assertStatus(500)->assertJsonPath('status', 'error')->assertJsonStructure(['reference'])->assertDontSee('SECRET');
        $this->get('/_test/failure')->assertStatus(500)->assertSee('Error reference:')->assertDontSee('SECRET');
    }

    public function test_validation_keeps_field_errors_and_does_not_flash_secrets(): void
    {
        $this->postJson('/_test/validation', ['password' => 'hidden'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->from('/products/create')->post('/_test/validation', ['password' => 'hidden', 'token' => 'hidden', 'description' => 'keep me'])
            ->assertRedirect('/products/create')->assertSessionHasErrors('name')->assertSessionHas('_old_input.description', 'keep me')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.token');
    }

    public function test_database_conflicts_are_safe_and_do_not_become_success(): void
    {
        $this->getJson('/_test/conflict')->assertStatus(409)->assertJsonPath('status', 'error')->assertDontSee('private');
    }

    public function test_retry_after_header_is_preserved(): void
    {
        Route::get('/_test/throttle', fn () => abort(429, 'secret', ['Retry-After' => '60']));
        $this->getJson('/_test/throttle')->assertStatus(429)->assertHeader('Retry-After', '60');
    }
}
