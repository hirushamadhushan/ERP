<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductReferencesTest extends TestCase
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
            throw new \RuntimeException('Only in-memory tests allowed.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_guests_cannot_access_or_create_categories_and_brands(): void
    {
        foreach (['categories', 'brands'] as $kind) {
            $this->get('/'.$kind)->assertRedirect('/login');
            $this->post('/'.$kind, ['name' => 'Test'])->assertRedirect('/login');
            $this->assertDatabaseCount($kind, 0);
        }
    }

    public function test_crud_and_validation_for_both_pages(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['categories', 'brands'] as $kind) {
            $data = ['name' => 'Test '.$kind, 'description' => 'Details'];
            if ($kind === 'categories') {
                $data['code'] = '0012';
            }
            $this->post('/'.$kind, $data)->assertSessionHasNoErrors()->assertRedirect('/'.$kind);
            $this->assertDatabaseHas($kind, $data);
            $id = DB::table($kind)->value('id');
            $this->get('/'.$kind)->assertOk()->assertSee($data['name'])->assertSee('Details');
            $this->post('/'.$kind, $data)->assertSessionHasErrors('name');
            $this->post('/'.$kind, ['name' => ''])->assertSessionHasErrors('name');
            $data['name'] = 'Renamed '.$kind;
            $data['description'] = null;
            $this->put('/'.$kind.'/'.$id, $data)->assertSessionHasNoErrors()->assertRedirect('/'.$kind);
            $this->assertDatabaseHas($kind, $data);
            $this->delete('/'.$kind.'/'.$id)->assertRedirect('/'.$kind);
            $this->assertDatabaseCount($kind, 0);
        }
    }

    public function test_escaped_content_and_optional_fields(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['categories', 'brands'] as $kind) {
            $this->post('/'.$kind, ['name' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
            $this->get('/'.$kind)->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        }
    }

    public function test_taxonomies_redirects_to_categories(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/taxonomies?type=product')->assertRedirect('/categories');
    }
}
