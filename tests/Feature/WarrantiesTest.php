<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warranty;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarrantiesTest extends TestCase
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

        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/2026_09_14_130000_create_warranties_table.php',
            ],
            '--force' => true,
        ])->assertExitCode(0);
    }

    public function test_guests_cannot_manage_warranties(): void
    {
        $this->get('/warranties')->assertRedirect('/login');
        $this->post('/warranties', ['name' => '1 Year', 'duration' => 1, 'duration_type' => 'years'])->assertRedirect('/login');
        $this->assertDatabaseCount('warranties', 0);
    }

    public function test_warranties_can_be_created_edited_and_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create
        $this->post('/warranties', [
            'name' => '1 Year Comprehensive Warranty',
            'description' => 'Covers all hardware repairs',
            'duration' => 1,
            'duration_type' => 'years',
        ])->assertSessionHasNoErrors()->assertRedirect('/warranties');

        $warranty = Warranty::firstOrFail();
        $this->assertEquals('1 Year Comprehensive Warranty', $warranty->name);
        $this->assertEquals('Covers all hardware repairs', $warranty->description);
        $this->assertEquals(1, $warranty->duration);
        $this->assertEquals('years', $warranty->duration_type);
        $this->assertEquals('1 Years', $warranty->formatted_duration);

        // Listing page check
        $this->get('/warranties')
            ->assertOk()
            ->assertSee('1 Year Comprehensive Warranty')
            ->assertSee('1 Years')
            ->assertSee('All Warranties');

        // Edit
        $this->put('/warranties/'.$warranty->id, [
            'name' => '6 Months Warranty',
            'description' => 'Covers parts only',
            'duration' => 6,
            'duration_type' => 'months',
        ])->assertSessionHasNoErrors()->assertRedirect('/warranties');

        $this->assertEquals('6 Months Warranty', $warranty->fresh()->name);
        $this->assertEquals('6 Months', $warranty->fresh()->formatted_duration);

        // Delete
        $this->delete('/warranties/'.$warranty->id)->assertRedirect('/warranties');
        $this->assertDatabaseCount('warranties', 0);
    }

    public function test_warranties_validation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Name required
        $this->post('/warranties', [
            'name' => '',
            'duration' => 1,
            'duration_type' => 'years',
        ])->assertSessionHasErrors('name');

        // Invalid duration type
        $this->post('/warranties', [
            'name' => 'Test Warranty',
            'duration' => 1,
            'duration_type' => 'decades',
        ])->assertSessionHasErrors('duration_type');
    }
}
