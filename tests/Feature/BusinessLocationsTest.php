<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Models\InvoiceScheme;
use App\Models\InvoiceLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessLocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_business_locations(): void
    {
        $this->get('/business-locations')->assertRedirect('/login');
        $this->post('/business-locations', [])->assertRedirect('/login');
    }

    public function test_business_locations_can_be_added_edited_and_deactivated(): void
    {
        $this->actingAs(User::factory()->create());
        $scheme = InvoiceScheme::where('is_default', true)->firstOrFail();
        $layout = InvoiceLayout::where('is_default', true)->firstOrFail();

        $this->get('/business-locations')->assertOk()->assertSee('Business Locations')->assertSee('aria-label="Settings"', false);
        $this->post('/business-locations', [
            'name' => 'North Warehouse', 'code' => 'NORTH-01', 'landmark' => 'Clock Tower',
            'city' => 'Anuradhapura', 'zip_code' => '50000', 'state' => 'North Central',
            'country' => 'Sri Lanka', 'price_group' => 'Retail',
            'invoice_scheme_id' => $scheme->id, 'invoice_layout_pos_id' => $layout->id, 'invoice_layout_sale_id' => $layout->id,
        ])->assertRedirect('/business-locations')->assertSessionHasNoErrors();

        $location = Location::where('code', 'NORTH-01')->firstOrFail();
        $this->assertSame('Anuradhapura', $location->city);
        $this->get('/business-locations')->assertOk()->assertSee('North Warehouse')->assertSee('Clock Tower');

        $this->put('/business-locations/'.$location->id, [
            'name' => 'North Warehouse', 'code' => 'NORTH-01', 'city' => 'Kurunegala',
            'invoice_scheme_id' => $scheme->id, 'invoice_layout_pos_id' => $layout->id, 'invoice_layout_sale_id' => $layout->id,
        ])->assertRedirect('/business-locations')->assertSessionHasNoErrors();
        $this->assertSame('Default', $location->fresh()->invoiceScheme->name);
        $this->assertSame('Kurunegala', $location->fresh()->city);

        $this->patch('/business-locations/'.$location->id.'/status')->assertRedirect('/business-locations');
        $this->assertFalse($location->fresh()->is_active);
        $this->assertDatabaseCount('locations', 1);
        $this->patch('/business-locations/'.$location->id.'/status')->assertRedirect('/business-locations');
        $this->assertTrue($location->fresh()->is_active);
    }

    public function test_location_code_must_be_unique(): void
    {
        $this->actingAs(User::factory()->create());
        $scheme = InvoiceScheme::where('is_default', true)->firstOrFail();
        $layout = InvoiceLayout::where('is_default', true)->firstOrFail();
        Location::create(['name' => 'Main', 'code' => 'MAIN']);

        $this->post('/business-locations', [
            'name' => 'Second', 'code' => 'MAIN', 'invoice_scheme_id' => $scheme->id,
            'invoice_layout_pos_id' => $layout->id, 'invoice_layout_sale_id' => $layout->id,
        ])->assertSessionHasErrors('code');
    }

    public function test_inactive_location_is_not_offered_for_new_products(): void
    {
        $this->actingAs(User::factory()->create());
        Location::create(['name' => 'Active Warehouse', 'code' => 'ACTIVE']);
        Location::create(['name' => 'Closed Warehouse', 'code' => 'CLOSED', 'is_active' => false]);

        $this->get('/products/create')->assertOk()
            ->assertSee('Active Warehouse')
            ->assertDontSee('Closed Warehouse');
    }
}
