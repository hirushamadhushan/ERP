<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaxRatesTest extends TestCase
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
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->actingAs(User::factory()->create());
    }

    public function test_single_and_group_taxes_are_created_and_rendered(): void
    {
        $this->post('/tax-rates', ['name' => 'VAT', 'amount' => 8, 'for_tax_group' => 0])->assertSessionHasNoErrors();
        $this->post('/tax-rates', ['name' => 'SSCL', 'amount' => 2.5, 'for_tax_group' => 1])->assertSessionHasNoErrors();
        $this->post('/tax-rates/groups', ['name' => 'VAT + SSCL', 'tax_rate_ids' => [1, 2]])->assertSessionHasNoErrors();

        $group = TaxRate::groups()->with('subTaxes')->firstOrFail();
        $this->assertEquals(10.5, $group->amount);
        $this->assertCount(2, $group->subTaxes);
        $this->assertDatabaseCount('group_sub_taxes', 2);
        $this->get('/tax-rates')->assertOk()->assertSee('VAT + SSCL')->assertSee('10.500%')->assertSee('For tax group only')->assertSee('name="_token"', false);
    }

    public function test_child_update_recalculates_groups_and_safe_delete_is_enforced(): void
    {
        $vat = TaxRate::create(['name' => 'VAT', 'amount' => 8]);
        $sscl = TaxRate::create(['name' => 'SSCL', 'amount' => 2.5, 'for_tax_group' => true]);
        $this->post('/tax-rates/groups', ['name' => 'Combined', 'tax_rate_ids' => [$vat->id, $sscl->id]])->assertSessionHasNoErrors();
        $group = TaxRate::groups()->firstOrFail();

        $this->put('/tax-rates/'.$vat->id, ['name' => 'VAT', 'amount' => 9, 'for_tax_group' => 0])->assertSessionHasNoErrors();
        $this->assertEquals(11.5, $group->fresh()->amount);
        $this->delete('/tax-rates/'.$vat->id)->assertSessionHasErrors('tax_rate');
        $this->delete('/tax-rates/groups/'.$group->id)->assertSessionHasNoErrors();
        $this->delete('/tax-rates/'.$vat->id)->assertSessionHasNoErrors();
    }

    public function test_product_uses_server_side_configured_rate_and_hides_group_only_tax(): void
    {
        $hidden = TaxRate::create(['name' => 'Component', 'amount' => 3, 'for_tax_group' => true]);
        $visible = TaxRate::create(['name' => 'VAT', 'amount' => 8]);
        $unit = Unit::create(['name' => 'Pieces', 'short_name' => 'Pc', 'allow_decimal' => false]);
        $location = Location::create(['name' => 'Warehouse', 'code' => 'WH']);

        $this->get('/products/create')->assertOk()->assertSee('VAT')->assertDontSee('Component');
        $this->post('/products', [
            'name' => 'Taxed Item', 'sku' => 'TAX-1', 'unit_id' => $unit->id, 'location_ids' => [$location->id],
            'barcode_type' => 'CODE128', 'manage_stock' => 1, 'enable_serial' => 0, 'not_for_selling' => 0,
            'product_type' => 'single', 'tax_rate_id' => $visible->id, 'tax_rate' => 99,
            'selling_price_tax_type' => 'exclusive', 'purchase_price' => 100, 'selling_price' => 125, 'save_action' => 'save',
        ])->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertEquals(8, $product->tax_rate);
        $this->assertEquals(108, $product->purchase_price_inc);
        $this->assertSame($visible->id, $product->tax_rate_id);
        $this->delete('/tax-rates/'.$visible->id)->assertSessionHasErrors('tax_rate');
        $this->post('/products', [
            'name' => 'Bad', 'sku' => 'BAD-1', 'unit_id' => $unit->id, 'location_ids' => [$location->id], 'barcode_type' => 'CODE128',
            'manage_stock' => 1, 'enable_serial' => 0, 'not_for_selling' => 0, 'product_type' => 'single',
            'tax_rate_id' => $hidden->id, 'tax_rate' => 3, 'selling_price_tax_type' => 'exclusive',
            'purchase_price' => 10, 'selling_price' => 12, 'save_action' => 'save',
        ])->assertSessionHasErrors('tax_rate_id');
    }
}
