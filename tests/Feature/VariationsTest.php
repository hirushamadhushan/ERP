<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VariationTemplate;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VariationsTest extends TestCase
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

    public function test_guests_cannot_manage_variations(): void
    {
        $this->get('/variation-templates')->assertRedirect('/login');
        $this->post('/variation-templates', ['name' => 'Sizes', 'values' => ['S', 'M']])->assertRedirect('/login');
        $this->assertDatabaseCount('variation_templates', 0);
    }

    public function test_variations_can_be_created_edited_and_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create
        $this->post('/variation-templates', [
            'name' => 'Denim',
            'values' => ['30 Beige', '31 Beige', '30 Black'],
        ])->assertSessionHasNoErrors()->assertRedirect('/variation-templates');

        $variation = VariationTemplate::firstOrFail();
        $this->assertEquals('Denim', $variation->name);
        $this->assertEquals(['30 Beige', '31 Beige', '30 Black'], $variation->values);

        // Page listing check
        $this->get('/variation-templates')
            ->assertOk()
            ->assertSee('Denim')
            ->assertSee('30 Beige, 31 Beige, 30 Black')
            ->assertSee('All variations');

        // Edit
        $this->put('/variation-templates/'.$variation->id, [
            'name' => 'Sizes',
            'values' => ['S', 'M', 'L'],
        ])->assertSessionHasNoErrors()->assertRedirect('/variation-templates');

        $this->assertEquals('Sizes', $variation->fresh()->name);
        $this->assertEquals(['S', 'M', 'L'], $variation->fresh()->values);

        // Delete
        $this->delete('/variation-templates/'.$variation->id)->assertRedirect('/variation-templates');
        $this->assertDatabaseCount('variation_templates', 0);
    }

    public function test_variations_route_redirect(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/variations')->assertRedirect('/variation-templates');
    }

    public function test_variations_validation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Required name validation
        $this->post('/variation-templates', [
            'name' => '',
            'values' => ['S'],
        ])->assertSessionHasErrors('name');

        // Empty values validation
        $this->post('/variation-templates', [
            'name' => 'Colors',
            'values' => ['', '  '],
        ])->assertSessionHasErrors('values');
    }
}
