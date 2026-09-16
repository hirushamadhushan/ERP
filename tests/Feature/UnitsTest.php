<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnitsTest extends TestCase
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
        $this->artisan('migrate', ['--path' => ['database/migrations/0001_01_01_000000_create_users_table.php', 'database/migrations/2026_09_14_100000_create_units_table.php'], '--force' => true])->assertExitCode(0);
    }

    public function test_guests_cannot_manage_units(): void
    {
        $this->get('/units')->assertRedirect('/login');
        $this->post('/units', [])->assertRedirect('/login');
    }

    public function test_units_can_be_created_edited_and_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/units', ['name' => 'Pieces', 'short_name' => 'Pc(s)', 'allow_decimal' => '0'])->assertSessionHasNoErrors()->assertRedirect('/units');
        $unit = Unit::firstOrFail();
        $this->assertFalse($unit->allow_decimal);
        $this->get('/units')->assertOk()->assertSee('Pieces')->assertSee('Units');
        $this->put('/units/'.$unit->id, ['name' => 'Kilograms', 'short_name' => 'kg', 'allow_decimal' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($unit->fresh()->allow_decimal);
        $this->delete('/units/'.$unit->id)->assertRedirect('/units');
        $this->assertDatabaseCount('units', 0);
    }

    public function test_multiple_conversion_and_base_deletion_protection(): void
    {
        $this->actingAs(User::factory()->create());
        $base = Unit::create(['name' => 'Pieces', 'short_name' => 'pc', 'allow_decimal' => false]);
        $this->post('/units', ['name' => 'Box', 'short_name' => 'box', 'allow_decimal' => 0, 'is_multiple' => 1, 'base_unit_id' => $base->id, 'base_unit_multiplier' => 12])->assertSessionHasNoErrors();
        $box = Unit::where('name', 'Box')->firstOrFail();
        $this->assertSame('12.000000', $box->base_unit_multiplier);
        $this->get('/units')->assertOk()->assertSee('1 box = 12 pc');
        $this->from('/units')->delete('/units/'.$base->id)->assertSessionHas('unit_error');
        $this->assertDatabaseCount('units', 2);
        $this->put('/units/'.$box->id, ['name' => 'Box', 'short_name' => 'box', 'allow_decimal' => 0])->assertSessionHasNoErrors();
        $this->assertNull($box->fresh()->base_unit_id);
        $this->assertNull($box->fresh()->base_unit_multiplier);
    }

    public function test_invalid_inputs_self_reference_and_nested_bases_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $base = Unit::create(['name' => 'Pieces', 'short_name' => 'pc', 'allow_decimal' => false]);
        $this->post('/units', ['name' => 'Pieces', 'short_name' => 'pc', 'allow_decimal' => 'maybe'])->assertSessionHasErrors(['name', 'short_name', 'allow_decimal']);
        $this->put('/units/'.$base->id, ['name' => 'Pieces', 'short_name' => 'pc', 'allow_decimal' => 0, 'is_multiple' => 1, 'base_unit_id' => $base->id, 'base_unit_multiplier' => 1])->assertSessionHasErrors('base_unit_id');
        $this->post('/units', ['name' => 'Bad', 'short_name' => 'bad', 'allow_decimal' => 0, 'is_multiple' => 1, 'base_unit_id' => $base->id, 'base_unit_multiplier' => 0])->assertSessionHasErrors('base_unit_multiplier');
        $box = Unit::create(['name' => 'Box', 'short_name' => 'box', 'allow_decimal' => false, 'base_unit_id' => $base->id, 'base_unit_multiplier' => 12]);
        $this->post('/units', ['name' => 'Case', 'short_name' => 'case', 'allow_decimal' => 0, 'is_multiple' => 1, 'base_unit_id' => $box->id, 'base_unit_multiplier' => 10])->assertSessionHasErrors('base_unit_id');
    }
}
